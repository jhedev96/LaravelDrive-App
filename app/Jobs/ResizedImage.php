<?php

namespace App\Jobs;

use App\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File as FileAdapter;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class ResizedImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 1;

    protected File $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function handle()
    {
        if ($this->file->type !== 'image') {
            return;
        }

        $sizes = $this->file->meta['sizes'] ?? null;

        // not assigned any sizes to generate, nothing to do
        if (! is_array($sizes)) {
            return;
        }

        $cloudDisk = config('filesystems.cloud');

        if ($this->file->driver === $cloudDisk
            && ! Storage::disk($cloudDisk)->exists($this->file->getStoragePath())) {
            Log::error('ResizedImage: source image is missing on the configured cloud disk.', [
                'file_id' => $this->file->id,
                'path' => $this->file->getStoragePath(),
            ]);

            return;
        }

        $path = storage_path("app/uploads/{$this->file->file_name}");

        foreach ($sizes as $key => $done) {
            // skip sizes already generated earlier
            if ($done === true) {
                continue;
            }

            $rs = explode('x', $key);

            if (count($rs) !== 2 || ! ctype_digit($rs[0]) || ! ctype_digit($rs[1])) {
                Log::warning('ResizedImage: invalid size key, skipping.', [
                    'file_id' => $this->file->id,
                    'size' => $key,
                ]);
                continue;
            }

            try {
                // Intervention 4: read() was renamed decodePath()
                $image = Image::decodePath("{$path}/{$this->file->name}")->resize((int) $rs[0], (int) $rs[1]);

                $localFile = "{$path}/{$key}-{$this->file->name}";
                $image->save($localFile);

                if ($this->file->driver === $cloudDisk) {
                    Storage::disk($cloudDisk)->putFileAs(
                        $this->file->file_name,
                        new FileAdapter($localFile),
                        "{$key}-{$this->file->name}",
                        ['visibility' => 'public']
                    );
                }

                $sizes[$key] = true;
            } catch (Throwable $e) {
                Log::error('ResizedImage: failed to generate resized image.', [
                    'file_id' => $this->file->id,
                    'size' => $key,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $meta = $this->file->meta ?? [];
        $meta['sizes'] = $sizes;
        $this->file->meta = $meta;

        if ($this->file->isDirty()) {
            $this->file->save();
        }
    }
}
