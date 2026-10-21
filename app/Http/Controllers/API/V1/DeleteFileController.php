<?php

namespace App\Http\Controllers\API\V1;

use App\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteFileController extends ApiController
{
    private Request $request;

    private File $file;

    public function __construct(Request $request, File $file)
    {
        $this->request = $request;
        $this->file = $file;
    }

    /**
     * Delete (soft/permanent) specified entries and their children.
     */
    public function delete()
    {
        $this->validate($this->request, [
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $fileIds = $this->request->input('ids');

        $this->deleteEntries($fileIds);

        return response()->json(['success' => true, 'message' => 'files deleted successfully.']);
    }

    /**
     * @param  array|Collection  $fileIds
     */
    private function deleteEntries($fileIds)
    {
        foreach ($this->file->withTrashed()->whereIn('id', $fileIds)->cursor() as $file) {
            if ($file->type === 'folder') {
                $this->deleteFolderFile($file);
            } else {
                $this->deleteEntry($file);
            }
        }
    }

    private function deleteEntry(File $original)
    {
        $action = $this->request->input('action');

        if ($action === 'deleteforever') {
            $this->deleteFileStorage($original);
            $this->forceDeleteModel($original);
        } elseif ($action === 'restore') {
            $this->restoreModel($original);
        } else {
            $this->deleteModel($original);
        }
    }

    private function deleteFolderFile(File $original)
    {
        $this->deleteChildEntries($original);
        $this->deleteEntry($original);
    }

    private function deleteChildEntries(File $original)
    {
        $fileIds = $this->file->where('parent_id', $original->id)->pluck('id');

        if (! $fileIds->isEmpty()) {
            $this->deleteEntries($fileIds);
        }
    }

    private function deleteModel(File $original)
    {
        return $original->delete();
    }

    private function restoreModel(File $original)
    {
        return $original->restore();
    }

    private function forceDeleteModel(File $original)
    {
        return $original->forceDelete();
    }

    private function deleteFileStorage(File $original)
    {
        Storage::disk($original->driver ?: 'local')->deleteDirectory($original->file_name);
    }

    public function trash(Request $request)
    {
        $parent_id = $request->input('parent_id');
        $perPage = 20;

        $files = File::onlyTrashed()
            ->orderByRaw('type = ? desc', ['folder'])
            ->where('uploaded_by', Auth::id())
            ->paginate($perPage);

        return JsonResource::collection($files);
    }
}
