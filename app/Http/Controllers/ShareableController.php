<?php

namespace App\Http\Controllers;

class ShareableController extends Controller
{
    /**
     * Public page for a shared entry, mounted by hash id.
     */
    public function __invoke(string $hash)
    {
        // W3.9 consumer page: the SPA reads window.LD.shareHash
        return view('sharedfiles')->with([
            'hash' => $hash,
            'csrfToken' => csrf_token(),
        ]);
    }
}
