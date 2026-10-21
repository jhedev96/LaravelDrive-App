<?php

namespace App\Services\Shares;

use Illuminate\Support\Facades\DB;

class DetachUsersFromEntries
{
    /**
     * Detach (non owner) users from specified entries.
     *
     * @param  array|\Illuminate\Support\Collection  $entryIds
     * @param  array|\Illuminate\Support\Collection  $userIds
     */
    public function execute($entryIds, $userIds)
    {
        DB::table('file_user')
            ->whereIn('file_id', $entryIds)
            ->whereIn('user_id', $userIds)
            ->where('owner', false)
            ->delete();
    }
}
