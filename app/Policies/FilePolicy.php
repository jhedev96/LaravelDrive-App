<?php

namespace App\Policies;

use App\File;
use App\User;
use Illuminate\Support\Facades\DB;

class FilePolicy
{
    /**
     * Determine whether the user owns all of the referenced entries
     * (or is an administrator). Entries may be folders, in which case
     * access to all children is granted implicitly.
     */
    public function update(User $user, $entryIds, ?string $ability = null): bool
    {
        $entryIds = (array) $entryIds;

        if (empty($entryIds)) {
            return false;
        }

        if ($user->hasPermission('administrator')) {
            return true;
        }

        // count matches, including entries shared with the user explicitly
        $owned = DB::table('files')
            ->where(function ($q) use ($user) {
                $q->where('uploaded_by', $user->id)
                    ->orWhereIn('id', DB::table('file_user')
                        ->where('user_id', $user->id)
                        ->select('file_id'));
            })
            ->whereIn('id', $entryIds)
            ->count();

        return $owned === count($entryIds);
    }
}
