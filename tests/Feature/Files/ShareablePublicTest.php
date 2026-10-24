<?php

namespace Tests\Feature\Files;

use App\File;
use App\ShareableLink;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/**
 * W3.9: the public (guest) shareable-link surface for /file/s/{hash}.
 * Stateless contract — owner-approved: the password travels with every
 * request, no session state. Expired links, entry-trashed links and
 * allow_download=off are refused; the legacy app never had a public side
 * at all (the sharedfiles blade was an empty shell).
 */
class ShareablePublicTest extends TestCase
{
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->owner = User::factory()->create();
    }

    protected function makeEntry(array $overrides = []): File
    {
        // files table knows only `uploaded_by` (user association for sharing
        // goes through the file_user pivot, not a files.user_id column)
        $entry = File::factory()->create(array_merge([
            'uploaded_by' => $this->owner->id,
            'name' => 'Report.txt',
            'type' => 'file',
            'extension' => 'txt',
            'mime' => 'text/plain',
        ], $overrides));

        $entry->file_size = (int) strlen('SHARE-ME-CONTENT');
        $entry->save();

        Storage::disk('local')->put($entry->getStoragePath(), 'SHARE-ME-CONTENT');

        return $entry->refresh();
    }

    protected function makeLink(File $entry, array $overrides = []): ShareableLink
    {
        return ShareableLink::create(array_merge([
            'user_id' => $this->owner->id,
            'file_id' => $entry->id,
            'hash' => Str::random(30),
            'allow_download' => true,
        ], $overrides));
    }

    public function test_guest_reads_link_with_file_payload(): void
    {
        $link = $this->makeLink($this->makeEntry());

        $response = $this->getJson("/api/share/links/{$link->hash}");

        $response->assertOk()
            ->assertJsonPath('data.requires_password', false)
            ->assertJsonPath('data.allow_download', true)
            ->assertJsonPath('data.file.name', 'Report.txt');
    }

    public function test_password_gated_link_stays_dark_until_checked(): void
    {
        $link = $this->makeLink($this->makeEntry(), ['password' => Hash::make('plainly')]);

        $dark = $this->getJson("/api/share/links/{$link->hash}");

        $dark->assertOk()
            ->assertJsonPath('data.requires_password', true);
        $this->assertNull($dark->json('data.file'));

        // no/wrong password → 422 (validation first) / 401 (mismatch)
        $this->postJson("/api/share/links/{$link->hash}/check")->assertStatus(422);
        $this->postJson("/api/share/links/{$link->hash}/check", ['password' => 'wrong'])
            ->assertStatus(401);

        // correct password → full payload in one round-trip
        $opened = $this->postJson("/api/share/links/{$link->hash}/check", ['password' => 'plainly']);

        $opened->assertOk()
            ->assertJsonPath('data.file.name', 'Report.txt');
    }

    public function test_download_respects_allow_download_flag(): void
    {
        // flag off → 403 even for an otherwise-open link
        $off = $this->makeLink($this->makeEntry(), ['allow_download' => false]);

        $this->postJson("/api/share/links/{$off->hash}/download")
            ->assertStatus(403);

        // flag on → streams the entry with the legacy slug filename
        $on = $this->makeLink($this->makeEntry(), ['allow_download' => true]);

        $response = $this->postJson("/api/share/links/{$on->hash}/download");

        $response->assertOk();
        $this->assertStringContainsString('report.txt', $response->headers->get('content-disposition'));
        $this->assertSame('SHARE-ME-CONTENT', file_get_contents($response->getFile()->getRealPath()));
    }

    public function test_password_gated_download_stays_stateless(): void
    {
        $link = $this->makeLink($this->makeEntry(), ['password' => Hash::make('plainly')]);

        // wrong → 401
        $this->postJson("/api/share/links/{$link->hash}/download", ['password' => 'nope'])
            ->assertStatus(401);

        // right → streams the contents (no session involved)
        $this->postJson("/api/share/links/{$link->hash}/download", ['password' => 'plainly'])
            ->assertOk();
    }

    public function test_expired_links_die_with_a_404(): void
    {
        $link = $this->makeLink($this->makeEntry());
        $link->update(['expires_at' => now()->subDay()]);

        $this->getJson("/api/share/links/{$link->hash}")->assertNotFound();
        $this->postJson("/api/share/links/{$link->hash}/check")->assertNotFound();
        $this->postJson("/api/share/links/{$link->hash}/download")->assertNotFound();
    }

    public function test_trashing_the_entry_kills_its_public_surface(): void
    {
        $entry = $this->makeEntry();
        $link = $this->makeLink($entry);

        $entry->delete();

        $this->getJson("/api/share/links/{$link->hash}")->assertNotFound();
        $this->postJson("/api/share/links/{$link->hash}/download")->assertNotFound();
    }

    public function test_folder_links_zip_the_children_for_guests(): void
    {
        $folder = File::factory()->create([
            'uploaded_by' => $this->owner->id,
            'type' => 'folder',
            'name' => 'FolderShare',
        ]);

        $child = File::factory()->create([
            'uploaded_by' => $this->owner->id,
            'type' => 'file',
            'name' => 'inside.txt',
            'extension' => 'txt',
            'mime' => 'text/plain',
            'parent_id' => $folder->id,
        ]);

        Storage::disk('local')->put($folder->getStoragePath(), 'placeholder');
        Storage::disk('local')->put($child->getStoragePath(), 'FOLDER-SHARE-CHILD');

        $link = $this->makeLink($folder);
        $link->refresh();

        $response = $this->postJson("/api/share/links/{$link->hash}/download");
        $response->assertOk();

        $zips = glob(storage_path('app/public/*.zip'));
        $this->assertNotEmpty($zips, 'a zip archive should have been streamed via app/public');

        $zip = new ZipArchive();
        $zip->open($zips[0]);
        // folder entries keep their raw names (legacy folderDownload behavior)
        $contents = (string) $zip->getFromName('FolderShare/inside.txt');

        $this->assertSame('FOLDER-SHARE-CHILD', $contents);
        $zip->close();
    }
}
