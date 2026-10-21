<?php

namespace App\Services\Shares;

use App\File;
use App\User;
use Illuminate\Database\Eloquent\Collection;

class GetUsersWithAccessToEntry
{
    private File $entry;

    public function __construct(File $entry)
    {
        $this->entry = $entry;
    }

    /**
     * @param  int  $entryId
     * @return Collection|User[]
     */
    public function execute($entryId)
    {
        return $this->entry->with('sharedWith')->findOrFail($entryId)->sharedWith;
    }
}
