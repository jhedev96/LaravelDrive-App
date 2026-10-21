<?php

namespace App\Http\Controllers\API\V1;

use App\File;
use App\Response\DownloadResponse;
use Illuminate\Http\Request;

class DownloadController extends ApiController
{
    private Request $request;

    private File $file;

    private DownloadResponse $downloadResponse;

    public function __construct(Request $request, File $file, DownloadResponse $downloadResponse)
    {
        $this->request = $request;
        $this->file = $file;
        $this->downloadResponse = $downloadResponse;
    }

    public function download(Request $request)
    {
        $this->validate($request, [
            'ids' => 'required|array|min:1',
        ]);

        $ids = $request->input('ids');

        if (count($ids) > 1) {
            return $this->downloadResponse->multipleDownload($ids);
        }

        return $this->downloadResponse->singleDownload($ids[0]);
    }
}
