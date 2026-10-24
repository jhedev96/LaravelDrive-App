<?php

namespace App\Http\Controllers\API\V1;

use App\File;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class CopyFileController extends ApiController
{
    /**
     * Make copies of all specified entries and their children.
     */
    public function copy(Request $request)
    {
        // Request injected into the method: the constructor variant keeps a
        // stale request instance across sequential test requests
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $fileIds = $request->input('ids');

        $copies = $this->copyEntries($fileIds);

        return JsonResource::collection($copies);
    }

    /**
     * @param  array|Collection  $fileIds
     * @param  int|null  $parentId
     * @return Collection
     */
    private function copyEntries($fileIds, $parentId = 0)
    {
        $copies = collect();

        // materialize: models are saved while iterating this same table
        $files = File::query()->whereIn('id', $fileIds)->get();

        foreach ($files as $file) {
            if ($file->type === 'folder') {
                $copies[] = $this->copyFolderFile($file, $parentId);
            } else {
                $copies[] = $this->copyFile($file, $parentId);
            }
        }

        return $copies;
    }

    private function copyFile(File $original, $parentId = 0)
    {
        $copy = $this->copyModel($original, $parentId);
        $this->copyFileStorage($original, $copy);

        return $copy;
    }

    private function copyFolderFile(File $original, $parentId = 0)
    {
        $copy = $this->copyModel($original, $parentId);
        $this->copyChildEntries($copy, $original);

        return $copy;
    }

    private function copyChildEntries(File $copy, File $original)
    {
        $fileIds = File::query()->where('parent_id', $original->id)->pluck('id');

        if (! $fileIds->isEmpty()) {
            $this->copyEntries($fileIds, $copy->id);
        }
    }

    private function copyModel(File $original, $parentId = 0)
    {
        // the `stared` global scope materializes a tag-name subselect as a raw
        // attribute; replicate must drop it or the copy INSERT 500s with an
        // unknown column (W4.16 — the copy endpoint was never exercised so it
        // was silently broken)
        $copy = $original->replicate(['stared']);

        $copy->file_name = Str::random(40);
        $copy->parent_id = $parentId;
        $copy->save();

        $copy->generatePath();

        return $copy;
    }

    private function copyFileStorage(File $original, File $copy)
    {
        // W3.4: copy within the disk the original lives on (copying a
        // driver-selected entry through `local` 404'ed)
        $disk = $original->driver;

        if (! in_array($disk, array_keys(config('filesystems.disks')), true)) {
            $disk = 'local';
        }

        Storage::disk($disk)->copy($original->getStoragePath(), $copy->getStoragePath());
    }

    /**
     * Get user to which file copies should be attached.
     */
    private function getCopyOwnerId()
    {
        return Auth::id();
    }
}
