<?php

namespace App\Http\Controllers\API\V1;

use App\Response\DownloadResponse;
use Illuminate\Http\Request;

class DownloadController extends ApiController
{
    private DownloadResponse $downloadResponse;

    public function __construct(DownloadResponse $downloadResponse)
    {
        $this->downloadResponse = $downloadResponse;
    }

    public function download(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
        ]);

        $ids = $request->input('ids');

        if (count($ids) > 1) {
            return $this->downloadResponse->multipleDownload($ids);
        }

        return $this->downloadResponse->singleDownload($ids[0]);
    }
}
