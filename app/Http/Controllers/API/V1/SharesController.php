<?php

namespace App\Http\Controllers\API\V1;

use App\File;
use App\Services\Shares\AttachUsersToEntry;
use App\Services\Shares\DetachUsersFromEntries;
use App\Services\Shares\GetUsersWithAccessToEntry;
use App\Services\Shares\UpdateEntryUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class SharesController extends ApiController
{
    public function sharedWithMe(Request $request)
    {
        $parent_id = $request->input('parent_id');
        $per_page = 20;

        // folder will be null if we don't share the root level
        $folder = File::find($parent_id);

        $files = $request->user()->sharedFiles()
            ->orderByRaw('type = ? desc', ['folder'])
            ->where('parent_id', $folder ? $folder->id : 0)
            ->paginate($per_page);

        return JsonResource::collection($files);
    }

    public function sharedWith(Request $request, $file_id)
    {
        $users = File::with('sharedWith')->findOrFail($file_id)->sharedWith;

        return JsonResource::collection($users);
    }

    /**
     * Share drive entries with specified users.
     */
    public function addUsers(Request $request, AttachUsersToEntry $action)
    {
        $request->validate([
            'userIds' => 'required|min:1',
            'userIds.*' => 'required|integer|exists:users,id',
            'permissions' => 'required|integer',
            'fileids' => 'required|min:1',
            'fileids.*' => 'required|integer',
        ]);

        $this->authorize('update', [File::class, $request->input('fileids')]);

        $action->execute(
            $request->input('userIds'),
            $request->input('fileids'),
            $request->input('permissions')
        );

        return $this->respondWithMessage('Files successfully shared with users.');
    }

    /**
     * Update permissions that specified users have for entries.
     */
    public function updateUsers(Request $request, UpdateEntryUsers $action)
    {
        $entryIds = $request->input('entries');

        $this->validate($request, [
            'entries' => 'required|array|min:1',
            'entries.*' => 'required|integer',
            'users' => 'required|array|min:1',
            'users.*.id' => 'required|exists:users,id',
            'users.*.permissions' => 'required|array',
            'users.*.removed' => 'boolean',
        ]);

        $action->execute(
            $request->input('users'),
            $entryIds
        );

        $users = app(GetUsersWithAccessToEntry::class)
            ->execute(Arr::first($entryIds));

        return $this->success(['users' => $users]);
    }

    /**
     * Detach user from specified entries.
     */
    public function removeUser(Request $request, DetachUsersFromEntries $action)
    {
        $entryIds = $request->input('entries');
        $userId = $request->input('user_id');

        // there's no need to authorize if the user is
        // trying to remove himself from the entry
        if ((int) $userId !== $request->user()->id) {
            $this->authorize('update', [File::class, $entryIds]);
        }

        $action->execute($entryIds, [$userId]);

        return $this->success();
    }
}
