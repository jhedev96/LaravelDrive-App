<?php

namespace Tests\Feature\Files;

use App\File;
use App\Jobs\UploadToCloud;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * W3.4: optional per-file storage `driver` on upload, honored by the physical
 * storage, single download, copy and forever-delete; plus W3.6 the
 * UploadToCloud offload job.
 */
class StorageDriverTest extends TestCase
{
    protected User $user;

    protected int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = User::factory()->create();

        $this->userId = $this->user->id;

        Passport::actingAs($this->user);

        config(['filesystems.default' => 'local', 'filesystems.cloud' => 's3']);
    }

    /** one-shot (non-chunked) upload request, contract-identical to Dropzone's final chunk */
    protected function upload(array $params = []): \Illuminate\Testing\TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('report.txt', 'DRIVER-CONTENT');

        return $this->post('/api/file', array_merge([
            'file' => $file,
            'dzuuid' => (string) \Illuminate\Support\Str::uuid(),
            'dzchunkindex' => 0,
            'dztotalchunkcount' => 1,
            'dztotalfilesize' => strlen('DRIVER-CONTENT'),
            'dzchunksize' => strlen('DRIVER-CONTENT'),
            'dzchunkbyteoffset' => 0,
            'parent_id' => 0,
            'path' => '',
        ], $params));
    }

    public function test_upload_with_explicit_driver_stores_on_that_disk(): void
    {
        Storage::fake('s3');

        $response = $this->upload(['driver' => 's3']);

        $response->assertStatus(201);
        $data = $response->json('data');

        $row = File::query()->findOrFail($data['id']);

        $this->assertSame('s3', $row->driver);

        // the entry lives on s3 and is NOT on the local uploads disk
        Storage::disk('s3')->assertExists($row->file_name.'/'.$row->name);
        Storage::disk('local')->assertMissing($row->file_name.'/'.$row->name);

        // public entries expose the url of their own disk
        $this->assertSame(
            Storage::disk('s3')->url($row->getStoragePath()),
            $row->public_path
        );
    }

    public function test_upload_rejects_unknown_driver(): void
    {
        Storage::fake('s3');

        $this->upload(['driver' => 'warp-drive'])->assertStatus(422);

        $this->assertSame(0, File::query()->count());
    }

    public function test_upload_without_driver_keeps_the_local_default(): void
    {
        Storage::fake('s3');

        $response = $this->upload();
        $response->assertStatus(201);

        $row = File::query()->findOrFail($response->json('data.id'));

        $this->assertSame('local', $row->driver);
        Storage::disk('local')->assertExists($row->file_name.'/'.$row->name);
    }

    public function test_single_download_streams_from_the_entry_disk(): void
    {
        Storage::fake('s3');

        $response = $this->upload(['driver' => 's3']);
        $row = File::query()->findOrFail($response->json('data.id'));

        $download = $this->post('/api/download', ['ids' => [$row->hash]]);

        $download->assertOk();
        // streamed from the fake s3 adapter (TestResponse buffers the stream)
        $this->assertSame('DRIVER-CONTENT', $download->streamedContent());
        $this->assertStringContainsString('report.txt', $download->headers->get('content-disposition'));
    }

    public function test_copy_dupes_storage_on_the_same_disk(): void
    {
        Storage::fake('s3');

        $response = $this->upload(['driver' => 's3']);
        $row = File::query()->findOrFail($response->json('data.id'));

        $copy = $this->post('/api/file/copy', ['ids' => [$row->id]]);

        $copyData = $copy->json('data.0');
        $copyRow = File::query()->findOrFail($copyData['id']);

        $this->assertSame('s3', $copyRow->driver);
        $this->assertNotSame($row->file_name, $copyRow->file_name);

        // original and copy both live on s3 with their own directories
        Storage::disk('s3')->assertExists($row->file_name.'/'.$row->name);
        Storage::disk('s3')->assertExists($copyRow->file_name.'/'.$copyRow->name);
    }

    public function test_deleteforever_purges_from_the_entry_disk(): void
    {
        Storage::fake('s3');

        $response = $this->upload(['driver' => 's3']);
        $row = File::query()->findOrFail($response->json('data.id'));

        // trash keeps storage (legacy contract), forever deletes it
        $this->delete('/api/file/delete', ['ids' => [$row->id]])->assertOk();
        Storage::disk('s3')->assertExists($row->file_name.'/'.$row->name);

        $this->delete('/api/file/delete', ['ids' => [$row->id], 'action' => 'deleteforever'])->assertOk();
        Storage::disk('s3')->assertMissing($row->file_name);
        $this->assertDatabaseMissing('files', ['id' => $row->id]);
    }

    public function test_uploadtocloud_job_offloads_local_storage_to_cloud_disk(): void
    {
        Storage::fake('s3');

        // real local disk (the legacy job hardcodes the app/uploads root)
        $dir = \Illuminate\Support\Str::random(36);
        Storage::disk('local')->put("$dir/report.txt", 'CLOUD-OFFLOAD');

        $row = File::create([
            'name' => 'report.txt',
            'file_name' => $dir,
            'mime' => 'text/plain',
            'type' => 'file',
            'extension' => 'txt',
            'path' => '',
            'parent_id' => 0,
            'user_id' => $this->userId,
            'uploaded_by' => $this->userId,
            'driver' => 'local',
            'driver_data' => null,
            'meta' => ['sizes' => ['120x80' => false], 'permissions' => ['public' => true]],
            'file_size' => 12,
        ]);

        (new UploadToCloud($row))->handle();

        $row->refresh();

        $this->assertSame('s3', $row->driver);
        Storage::disk('s3')->assertExists("$dir/report.txt");
        Storage::disk('local')->assertDirectoryEmpty($dir);
        $this->assertSame(Storage::disk('s3')->url($row->getStoragePath()), $row->public_path);

        // upload_towards an already-cloud entry is a no-op
        (new UploadToCloud($row))->handle();
        $this->assertSame('s3', $row->driver);
    }
}
