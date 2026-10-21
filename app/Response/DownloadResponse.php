<?php

namespace App\Response;

use App\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class DownloadResponse
{
    private $zipper;

    private File $file;

    public function __construct(File $file)
    {
        $this->file = $file;
        $this->zipper = new ZipArchive();
    }

    /**
     * Download a single file or, for folders, a zip archive of the folder contents.
     *
     * @param  string  $hash  id or hash of the entry
     */
    public function singleDownload($id)
    {
        $upload = $this->file->withTrashed()->whereHash($id)->firstOrFail();

        if ($upload->type === 'folder') {
            return $this->folderDownload($upload);
        }

        $headers = [
            'Content-Type' => $upload->mime,
        ];

        $filename = Str::slug(Str::before($upload->name, '.'), '-');
        $filename = "$filename.$upload->extension";

        $filepath = Storage::disk('local')->path($upload->getStoragePath());

        return response()->download($filepath, $filename, $headers);
    }

    public function folderDownload(File $upload)
    {
        $zip_name = Str::random(10);
        $zip_name = config('app.name').'-'.$zip_name;

        $zip_path = storage_path("app/public/$zip_name.zip");

        if ($this->zipper->open($zip_path, ZipArchive::CREATE) === true) {
            $this->fileRecussive($upload);
            $this->zipper->close();
        } else {
            abort(500, 'Could not create zip archive.');
        }

        return response()->download($zip_path, "$zip_name.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    public function fileRecussive(File $file, $folderName = '')
    {
        if ($file->type === 'folder') {
            $folderName = $folderName ? "$folderName/$file->name" : $file->name;

            foreach (File::withTrashed()->where('parent_id', $file->id)->cursor() as $child) {
                $this->fileRecussive($child, $folderName);
            }
        } else {
            $filename = Str::slug(Str::before($file->name, '.'), '-');
            $filename = "$filename.$file->extension";

            // zip archive can't have empty folders unless it's added first
            if ($folderName) {
                $this->zipper->addEmptyDir($folderName);
            }

            $content = Storage::disk($file->driver ?: 'local')->get($file->getStoragePath());

            if ($folderName) {
                $this->zipper->addFromString("$folderName/$filename", $content);
            } else {
                $this->zipper->addFromString($filename, $content);
            }
        }
    }

    public function multipleDownload($ids)
    {
        $zip_name = Str::random(10);
        $zip_name = config('app.name').'-'.$zip_name;

        $zip_path = storage_path("app/public/$zip_name.zip");

        if ($this->zipper->open($zip_path, ZipArchive::CREATE) === true) {
            if (is_array($ids)) {
                foreach (File::withTrashed()->whereIn('id', $ids)->cursor() as $child) {
                    $this->fileRecussive($child);
                }
            }

            $this->zipper->close();
        } else {
            abort(500, 'Could not create zip archive.');
        }

        return response()->download($zip_path, "$zip_name.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
