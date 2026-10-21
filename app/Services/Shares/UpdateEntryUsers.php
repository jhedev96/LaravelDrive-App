<?php

namespace App\Services\Shares;

use App\File;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Shares\Traits\GeneratesSharePermissions;

class UpdateEntryUsers
{
    use GeneratesSharePermissions;

    /**
     * Update users and their permissions for specified entries.
     *
     * @param  array  $users
     * @param  array|Collection  $entries
     */
    public function execute($users, $entries)
    {
        $users = collect($users);
        $entries = collect($entries);

        $users = $users->map(function ($user) {
            $user['permissions'] = $this->generateSharePermissions($user['permissions']);

            return $user;
        });

        $entriesAndChildren = $this->loadChildEntries($entries);

        // detach users (except owner) from entries
        app(DetachUsersFromEntries::class)->execute(
            $entriesAndChildren->pluck('id'),
            $users->pluck('id')
        );

        // filter out removed users, so they are not re-attached
        $users = $users->filter(fn ($user) => ! Arr::get($user, 'removed'));

        $records = $this->createPivotRecords($users, $entriesAndChildren, false);

        if ($records->isEmpty()) {
            return;
        }

        DB::table('file_user')->insert($records->toArray());
    }

    /**
     * Fetch all children of specified entries.
     */
    protected function loadChildEntries(Collection $entries)
    {
        $builder = File::select('id', 'file_name', 'path', 'type');

        if (is_numeric($entries->first())) {
            $entries = File::whereIn('id', $entries)->get();
        }

        $entries->each(function (File $entry) use ($builder) {
            if ($entry->type === 'folder') {
                $path = $entry->getOriginal('path');
                $builder->orWhere('path', 'LIKE', "$path/%");
            }
        });

        if (count($builder->getQuery()->wheres)) {
            $children = $builder->get();
            $entries = $entries->merge($children);
        }

        return $entries;
    }

    /**
     * Create pivot records without loading children (children already loaded).
     */
    protected function createPivotRecords($users, $entries, $loadChildren = true)
    {
        $now = now();

        $entryIds = $entries->map(fn ($entry) => is_numeric($entry) ? $entry : $entry->id);

        $records = $users->map(function ($user) use ($entryIds, $now) {
            return $entryIds->map(fn ($id) => [
                'user_id' => $user['id'],
                'file_id' => $id,
                'permissions' => json_encode($user['permissions']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        })->collapse();

        $existing = DB::table('file_user')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereIn('file_id', $entryIds)
            ->get();

        return $records->filter(function ($new) use ($existing) {
            return ! $existing->contains(fn ($current) => $current->file_id == $new['file_id'] && $current->user_id == $new['user_id']);
        });
    }
}
