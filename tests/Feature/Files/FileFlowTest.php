<?php

namespace Tests\Feature\Files;

use App\File;
use App\Plan;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;
use ZipArchive;

class FileFlowTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = User::factory()->create();

        Passport::actingAs($this->user);
    }

    /**
     * Dropzone-style chunk request (contract per pion DropZoneUploadHandler).
     */
    protected function chunkRequest(int $index, int $total, string $uuid, int $totalSize, UploadedFile $file): array
    {
        $chunkSize = (int) ceil($totalSize / $total);

        return [
            'file' => $file,
            'dzuuid' => $uuid,
            'dzchunkindex' => $index,
            'dztotalchunkcount' => $total,
            'dztotalfilesize' => $totalSize,
            'dzchunksize' => $chunkSize,
            'dzchunkbyteoffset' => $index * $chunkSize,
            'parent_id' => 0,
            'path' => '',
        ];
    }

    public function test_chunked_upload_assembles_file_and_storage_layout(): void
    {
        Storage::fake('local');

        $content = str_repeat('CHUNKED-CONTENT-', 20);
        $half = (int) (strlen($content) / 2);
        $uuid = 'cb5a1e54-1111-2222-3333-444455556666';

        // chunk 1 answers the mid-upload progress contract {done, status}
        $this->post('/api/file', $this->chunkRequest(
            0,
            2,
            $uuid,
            strlen($content),
            UploadedFile::fake()->createWithContent('notes.jpg', substr($content, 0, $half))
        ))->assertOk()->assertJsonPath('status', true)->assertJsonPath('done', 50);

        // no entry should persist until the final chunk arrives
        $this->assertSame(0, File::query()->count());

        // chunk 2 completes → the file resource is returned under data
        $response = $this->post('/api/file', $this->chunkRequest(
            1,
            2,
            $uuid,
            strlen($content),
            UploadedFile::fake()->createWithContent('notes.jpg', substr($content, $half))
        ));

        // JsonResource answers 201 for freshly created entries (legacy behavior)
        $response->assertStatus(201);
        $data = $response->json('data');

        $this->assertNotNull($data['hash'] ?? null);
        $this->assertSame('notes.jpg', $data['name']);
        $this->assertSame(strlen($content), (int) $data['file_size']);

        // legacy layout preserved: upload/{file_name}/{name} (file_name stays
        // hidden in API responses, so pull the row for the storage assertions)
        $entry = File::query()->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($entry->getStoragePath()));
        $this->assertSame($content, Storage::disk('local')->get($entry->getStoragePath()));

        $this->assertSame(1, File::query()->count());
    }

    public function test_single_download_streams_the_entry_contents(): void
    {
        $entry = File::factory()->create([
            'uploaded_by' => $this->user->id,
            'name' => 'Report.pdf',
            'extension' => 'pdf',
            'mime' => 'application/pdf',
        ]);

        Storage::disk('local')->put($entry->getStoragePath(), 'PDFBYTES');

        $response = $this->post('/api/download', ['ids' => [$entry->hash]]);

        $response->assertOk();
        // legacy parity: the download filename is slugified to lowercase
        $this->assertStringContainsString('report.pdf', $response->headers->get('content-disposition'));
        // BinaryFileResponse does not buffer its content — read it back from the file
        $this->assertSame('PDFBYTES', file_get_contents($response->getFile()->getRealPath()));
    }

    public function test_folder_download_zips_children_inside_the_folder_layout(): void
    {
        $folder = File::factory()->create([
            'uploaded_by' => $this->user->id,
            'type' => 'folder',
            'name' => 'My Folder',
            'file_name' => 'folder-raw',
        ]);

        $child = File::factory()->create([
            'uploaded_by' => $this->user->id,
            'name' => 'child.txt',
            'extension' => 'txt',
            'mime' => 'text/plain',
            'type' => 'file',
            'file_name' => 'child-raw',
            'parent_id' => $folder->id,
        ]);

        Storage::disk('local')->put($child->getStoragePath(), 'CHILD');

        $response = $this->post('/api/download', ['ids' => [$folder->hash]]);

        $response->assertOk();

        // BinaryFileResponse::getContent() is false in tests — inspect the
        // actual zip the controller wrote under storage/app/public
        $zips = glob(storage_path('app/public/*.zip'));
        $this->assertNotEmpty($zips, 'no zip archive was written');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zips[0]) === true);
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $zip->close();

        // the folder download used to drop children into the zip root — fixed
        $this->assertTrue(
            $names->contains(fn ($name) => str_contains((string) $name, 'My Folder/child.txt')),
            'zip entries: '.implode(' | ', $names->all())
        );
    }

    public function test_trash_then_delete_forever_removes_storage_and_row(): void
    {
        $entry = File::factory()->create([
            'uploaded_by' => $this->user->id,
            'name' => 'gone.txt',
            'mime' => 'text/plain',
        ]);

        Storage::disk('local')->put($entry->getStoragePath(), 'DATA');

        // default action = soft delete (trash keeps the storage content)
        $this->delete('/api/file/delete', ['ids' => [$entry->id]])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted($entry);
        $this->assertTrue(Storage::disk('local')->exists($entry->getStoragePath()));

        $this->delete('/api/file/delete', ['ids' => [$entry->id], 'action' => 'deleteforever'])
            ->assertOk();

        $this->assertNull(File::withTrashed()->find($entry->id), 'row survived deleteforever');
        $this->assertFalse(Storage::disk('local')->exists($entry->getStoragePath()));
    }

    public function test_index_lists_the_users_own_entries(): void
    {
        File::factory()->create(['uploaded_by' => $this->user->id, 'name' => 'mine.txt']);
        // another user's entry must never leak into the listing
        File::factory()->create([
            'uploaded_by' => User::factory()->create()->id,
            'name' => 'not-mine.txt',
        ]);

        $this->getJson('/api/file?parent_id=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'mine.txt');
    }

    public function test_upload_is_rejected_over_quota_when_billing_enabled(): void
    {
        config(['billing.enabled' => true]);

        $plan = Plan::query()->create(['slug' => 'tiny3', 'name' => 'Tiny3', 'max_storage' => 100]);
        $user = User::factory()->create();
        $user->forceFill(['plan_id' => $plan->id])->save();

        Passport::actingAs($user);

        $response = $this->post('/api/file', $this->chunkRequest(
            0,
            1,
            'aa1e54a2-0000-1111-2222-333344445555',
            5000,
            UploadedFile::fake()->createWithContent('big.jpg', str_repeat('x', 5000))
        ));

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'quota_exceeded');

        $this->assertSame(0, File::query()->where('uploaded_by', $user->id)->count());
    }

    public function test_upload_succeeds_within_quota_when_billing_enabled(): void
    {
        config(['billing.enabled' => true]);

        $plan = Plan::query()->create(['slug' => 'tiny4', 'name' => 'Tiny4', 'max_storage' => 1000]);
        $user = User::factory()->create();
        $user->forceFill(['plan_id' => $plan->id])->save();

        Passport::actingAs($user);

        $response = $this->post('/api/file', $this->chunkRequest(
            0,
            1,
            'aa2e54b2-0000-1111-2222-333344445555',
            900,
            UploadedFile::fake()->createWithContent('small.jpg', str_repeat('x', 900))
        ));

        $response->assertStatus(201);
        $this->assertSame(900, (int) $response->json('data.file_size'));
    }
}
