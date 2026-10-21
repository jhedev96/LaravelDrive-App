<?php

namespace App\Observers;

use App\Folder;
use Illuminate\Support\Facades\Auth;

class FolderObserver
{
    public function creating(Folder $folder)
    {
        $folder->uploaded_by = $folder->uploaded_by ?: Auth::id();
    }

    public function deleting(Folder $folder)
    {
        if (Auth::id()) {
            $folder->deleted_by = Auth::id();
        }
    }
}
