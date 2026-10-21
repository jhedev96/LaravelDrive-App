<?php

namespace App\Http\Controllers;

class ShareableController extends Controller
{
    /**
     * Public page for a shared entry, mounted by hash id.
     */
    public function __invoke($id)
    {
        return view('sharedfiles');
    }
}
