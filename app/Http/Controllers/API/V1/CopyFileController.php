<?php

namespace App\Http\Controllers\API\V1;

use App\File;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class CopyFileController extends ApiController
{
    private Request $request;

    private File $file;

    public function __construct(Request $request, File $file)
    {
        $this->request = $request;
        $this->file = $file;
    }

    /**
     * Make copies of all specified entries and their children.
     */
    public function copy()
    {
        $this->validate($this->request, [
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $fileIds = $this->request->input('ids');

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

        foreach ($this->file->whereIn('id', $fileIds)->cursor() as $file) {
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
        $fileIds = $this->file->where('parent_id', $original->id)->pluck('id');

        if (! $fileIds->isEmpty()) {
            $this->copyEntries($fileIds, $copy->id);
        }
    }

    private function copyModel(File $original, $parentId = 0)
    {
        $copy = $original->replicate();
        $copy->file_name = Str::random(40);
        $copy->parent_id = $parentId;
        $copy->save();

        $copy->generatePath();

        return $copy;
    }

    private function copyFileStorage(File $original, File $copy)
    {
        Storage::disk('local')->copy($original->getStoragePath(), $copy->getStoragePath());
    }

    /**
     * Get user to which file copies should be attached.
     */
    private function getCopyOwnerId()
    {
        return Auth::id();
    }
}
