<?php

namespace App\Response;

use App\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AudioVideoResponse
{
    /**
     * Stream specified video/audio file with HTTP Range support.
     *
     * @param  File  $entry
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function create(File $entry)
    {
        $disk = Storage::disk($entry->driver ?: 'local');
        $path = $entry->getStoragePath();

        if (! $disk->exists($path)) {
            abort(404);
        }

        $size = $disk->size($path);
        $time = date('r', $disk->lastModified($path));

        $begin = 0;
        $end = $size - 1;
        $partial = false;

        if (isset($_SERVER['HTTP_RANGE'])
            && preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)
        ) {
            $partial = true;
            $begin = intval($matches[1]);
            if (! empty($matches[2])) {
                $end = intval($matches[2]);
            }
        }

        if ($begin > $end || $begin >= $size) {
            abort(416, "invalid requested range: $begin-$end / $size");
        }

        $headers = [
            'Content-Type' => $entry->mime,
            'Cache-Control' => 'public, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (($end - $begin) + 1),
            'Content-Disposition' => "inline; filename={$entry->file_name}",
            'Content-Transfer-Encoding' => 'binary',
            'Last-Modified' => $time,
        ];

        if ($partial) {
            $headers['Content-Range'] = "bytes $begin-$end/$size";
        }

        $stream = $disk->readStream($path);

        return response()->stream(function () use ($stream, $begin, $end) {
            fseek($stream, $begin);

            $cur = $begin;
            while (! feof($stream) && $cur <= $end && connection_status() == 0) {
                $chunk = fread($stream, min(1024 * 16, ($end - $cur) + 1));
                if ($chunk === false) {
                    break;
                }
                print $chunk;
                flush();
                $cur += 1024 * 16;
            }

            fclose($stream);
        }, $partial ? 206 : 200, $headers);
    }
}
