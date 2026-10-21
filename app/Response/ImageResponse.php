<?php

namespace App\Response;

use App\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ImageResponse
{
    /**
     * Create response for previewing specified image.
     *
     * @param  File  $upload
     * @return Response
     */
    public function create(File $upload)
    {
        $content = Storage::disk($upload->driver ?: 'local')->get($upload->getStoragePath());

        return response($content, 200, ['Content-Type' => $upload->mime]);
    }
}
