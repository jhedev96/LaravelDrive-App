<?php

namespace App\Services\Shares;

use App\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Shares\Traits\GeneratesSharePermissions;
use App\User;

class AttachUsersToEntry
{
    use GeneratesSharePermissions;

    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Attach specified users to file entries.
     *
     * @param  array  $userids
     * @param  array  $fileids
     * @param  array  $permissions
     */
    public function execute($userids, $fileids, $permissions)
    {
        $files = collect($fileids);

        $users = $this->user->whereIn('id', $userids)->pluck('id')->map(function ($id) use ($permissions) {
            return ['id' => $id, 'permissions' => $this->generateSharePermissions($permissions)];
        });

        $records = $this->createPivotRecords($users, $files);

        if ($records->isEmpty()) {
            return;
        }

        DB::table('file_user')->insert($records->toArray());
    }

    /**
     * Create records for inserting into the file_user pivot table.
     *
     * @param  Collection  $users
     * @param  Collection  $entries
     * @param  bool  $loadChildren
     * @return Collection
     */
    protected function createPivotRecords($users, $entries, $loadChildren = true)
    {
        $now = now();

        $entriesAndChildren = $loadChildren ?
            $this->loadChildEntries($entries)->pluck('id') :
            collect($entries);

        $records = $users->map(function ($user) use ($entriesAndChildren, $now) {
            return $entriesAndChildren->map(function ($entry) use ($user, $now) {
                return [
                    'user_id' => $user['id'],
                    'file_id' => is_numeric($entry) ? $entry : $entry->id,
                    'permissions' => json_encode($user['permissions']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            });
        })->collapse();

        // remove duplicates: a shared folder might contain entries that are
        // already shared with some of the specified users
        $existing = DB::table('file_user')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereIn('file_id', $records->pluck('file_id'))
            ->get();

        return $records->filter(function ($new) use ($existing) {
            return ! $existing->contains(function ($current) use ($new) {
                return $current->file_id == $new['file_id'] && $current->user_id == $new['user_id'];
            });
        });
    }

    /**
     * Fetch all children of specified entries.
     *
     * @param  Collection  $entries
     * @param  bool  $withTrashed
     * @return Collection
     */
    protected function loadChildEntries(Collection $entries, $withTrashed = false)
    {
        $builder = File::select('id', 'file_name', 'path', 'type');

        // load parent entries, if we got only IDs passed in
        if (is_numeric($entries->first())) {
            $entries = File::whereIn('id', $entries)->get();
        }

        if ($withTrashed) {
            $builder->withTrashed();
        }

        $entries->each(function (File $entry) use ($builder) {
            if ($entry->type === 'folder') {
                $path = $entry->getOriginal('path');
                $builder->orWhere('path', 'LIKE', "$path/%");
            }
        });

        // only fetch children if any "where" constraints were applied
        if (count($builder->getQuery()->wheres)) {
            $children = $builder->get();
            $entries = $entries->merge($children);
        }

        return $entries;
    }
}
