<?php

namespace App\Http\Controllers;

use App\File;
use App\Response\FileContentResponseCreator;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * Public content streams for entries that have a "public" permission
     * (route /uploads/{id}).
     */
    public function __invoke(Request $request, string $id)
    {
        $entry = File::withTrashed()->where('file_name', $id)->first();

        abort_if($entry === null, 404, 'File not found.');

        $response = app(FileContentResponseCreator::class)->create($entry);

        // enforce the "public" permission for the unauthenticated route
        if (! $entry->hasPermission('public')) {
            abort(403, 'This file is not public.');
        }

        return $response;
    }
}
