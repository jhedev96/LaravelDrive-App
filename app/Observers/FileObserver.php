<?php

namespace App\Observers;

use App\File;
use Illuminate\Support\Facades\Auth;

class FileObserver
{
    public function creating(File $file)
    {
        $file->uploaded_by = $file->uploaded_by ?: Auth::id();
    }

    public function deleting(File $file)
    {
        if (Auth::id()) {
            $file->deleted_by = Auth::id();
        }
    }
}
