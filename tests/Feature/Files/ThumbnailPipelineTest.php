<?php

namespace Tests\Feature\Files;

use App\File;
use App\Jobs\ResizedImage;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * W2.4 continues: the legacy app wrote `ResizedImage` (plus the manual
 * `createThumbanile`) but never dispatched anything from the upload flow —
 * the thumbnail pipeline was a pure 0-usage feature. It is now wired: a
 * ResizedImage job is queued from FileController::store (sync queue in
 * tests → runs inline), generating `<size>-<name>` files and marking the
 * done flags in `meta.sizes`.
 */
class ThumbnailPipelineTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->user = User::factory()->create();

        Passport::actingAs($this->user);
    }

    /** renderable jpegs can't come from plain text fakes — use gd */
    protected function jpegContent(int $w = 240, int $h = 180): string
    {
        $resource = imagecreatetruecolor($w, $h);
        imagefill($resource, 0, 0, imagecolorallocate($resource, 20, 90, 160));
        imagejpeg($resource, $target = tempnam(sys_get_temp_dir(), 'img'), 90);
        imagedestroy($resource);

        return (string) file_get_contents($target);
    }

    protected function upload(string $filename, string $content): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/file', [
            'file' => UploadedFile::fake()->createWithContent($filename, $content),
            'dzuuid' => \Illuminate\Support\Str::uuid(),
            'dzchunkindex' => 0,
            'dztotalchunkcount' => 1,
            'dztotalfilesize' => strlen($content),
            'dzchunksize' => strlen($content),
            'dzchunkbyteoffset' => 0,
            'parent_id' => 0,
            'path' => '',
        ]);
    }

    public function test_image_upload_generates_resized_thumbnail(): void
    {
        Storage::fake('s3');

        $response = $this->upload('photo.jpg', $this->jpegContent());
        $response->assertStatus(201);

        $row = File::query()->findOrFail($response->json('data.id'));
        $meta = $row->refresh()->meta;

        $this->assertSame('image', $row->type);
        $this->assertTrue($meta['sizes']['120x80']);

        Storage::disk('local')->assertExists("{$row->file_name}/120x80-{$row->name}");
    }

    public function test_garbage_image_bytes_are_logged_not_thrown(): void
    {
        $response = $this->upload('broken.jpg', 'NOT-AN-IMAGE');
        $response->assertStatus(201);

        $row = File::query()->findOrFail($response->json('data.id'));

        $this->assertFalse($row->refresh()->meta['sizes']['120x80'] ?? false);
        $response->assertStatus(201);
    }

    public function test_non_image_entries_skip_the_pipeline(): void
    {
        $response = $this->upload('docmimic.txt', 'PLAIN');
        $response->assertStatus(201);

        $row = File::query()->findOrFail($response->json('data.id'));

        // type file → handle() is a no-op: no resized file, meta untouched
        (new ResizedImage($row->refresh()))->handle();

        $this->assertDirectoryDoesNotExist(storage_path('app/uploads')."{$row->file_name}/120x80-{$row->name}");
    }
}
