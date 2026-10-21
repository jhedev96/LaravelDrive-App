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

class UploadToCloud implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected File $file;

    public function __construct(File $file)
    {
        $this->file = $file;
    }

    public function handle()
    {
        $disk = config('filesystems.cloud');

        // nothing to do if it's already stored on the cloud disk
        if ($this->file->driver === $disk) {
            return;
        }

        $basePath = storage_path('app/uploads/');

        $localFiles = Storage::disk('local')->allFiles($this->file->file_name);

        foreach ($localFiles as $localFile) {
            Storage::disk($disk)->putFileAs(
                $this->file->file_name,
                new FileAdapter($basePath.$localFile),
                basename($localFile),
                ['visibility' => 'public']
            );
        }

        $this->file->updatePublicPaths($disk);

        Storage::disk('local')->deleteDirectory($this->file->file_name);

        Log::info('UploadToCloud: file moved to cloud disk.', [
            'file_id' => $this->file->id,
            'disk' => $disk,
        ]);
    }
}
