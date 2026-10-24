<?php

namespace App\Http\Controllers\API\V1;

use App\Events\FileEntriesChanged;
use App\File;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteFileController extends ApiController
{
    /**
     * Delete (soft/permanent) specified entries and their children.
     */
    public function delete(Request $request)
    {
        // the Request is method-injected: constructor injection is a known
        // stale-instance pitfall in the testing environment (action fell back
        // to soft-delete because the old request lacked it)
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $action = $request->input('action');

        $this->deleteEntries($request->input('ids'), $action);

        // W3.2: live refresh of the acting user's drive channel
        $hashes = File::query()->withTrashed()->whereIn('id', $request->input('ids'))
            ->get()->map(fn (File $file) => $file->hash)->all();

        FileEntriesChanged::dispatch($hashes, $action ?: 'trash', Auth::id());

        return response()->json(['success' => true, 'message' => 'files deleted successfully.']);
    }

    /**
     * @param  array|Collection  $fileIds
     * @param  string|null  $action
     */
    private function deleteEntries($fileIds, string $action = null)
    {
        // materialize before mutating: force-deleting rows while lazily
        // iterating the same table (cursor) is undefined behavior on SQLite
        $files = File::query()->withTrashed()->whereIn('id', $fileIds)->get();

        foreach ($files as $file) {
            if ($file->type === 'folder') {
                $this->deleteFolderFile($file, $action);
            } else {
                $this->deleteEntry($file, $action);
            }
        }
    }

    private function deleteEntry(File $original, ?string $action)
    {
        if ($action === 'deleteforever') {
            $this->deleteFileStorage($original);
            $this->forceDeleteModel($original);
        } elseif ($action === 'restore') {
            $this->restoreModel($original);
        } else {
            $this->deleteModel($original);
        }
    }

    private function deleteFolderFile(File $original, ?string $action)
    {
        $this->deleteChildEntries($original, $action);
        $this->deleteEntry($original, $action);
    }

    private function deleteChildEntries(File $original, ?string $action)
    {
        $fileIds = File::query()->where('parent_id', $original->id)->pluck('id');

        if (! $fileIds->isEmpty()) {
            $this->deleteEntries($fileIds, $action);
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
