<?php

namespace App\Response;

use App\File;
use Illuminate\Support\Facades\Storage;

class FileContentResponseCreator
{
    private ImageResponse $imageResponse;

    private AudioVideoResponse $audioVideoResponse;

    public function __construct(ImageResponse $imageResponse, AudioVideoResponse $audioVideoResponse)
    {
        $this->imageResponse = $imageResponse;
        $this->audioVideoResponse = $audioVideoResponse;
    }

    /**
     * Return download or preview response for the given file.
     */
    public function create(File $upload)
    {
        if (! Storage::disk($upload->driver ?: 'local')->exists($upload->getStoragePath())) {
            abort(404);
        }

        [$mime, $type] = $this->getTypeFromModel($upload);

        if ($type === 'image') {
            return $this->imageResponse->create($upload);
        } elseif ($this->shouldStream($mime, $type)) {
            return $this->audioVideoResponse->create($upload);
        } else {
            return $this->createBasicResponse($upload);
        }
    }

    private function createBasicResponse(File $upload)
    {
        return response(
            Storage::disk($upload->driver ?: 'local')->get($upload->getStoragePath()),
            200,
            ['Content-Type' => $upload->mime]
        );
    }

    /**
     * Extract file type from model.
     *
     * @return array
     */
    private function getTypeFromModel(File $fileModel)
    {
        $mime = $fileModel->mime;
        $type = explode('/', $mime)[0];

        return [$mime, $type];
    }

    private function shouldStream($mime, $type)
    {
        return $type === 'video' || $type === 'audio' || $mime === 'application/ogg';
    }
}
