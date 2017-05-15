<?php

namespace CatLab\Assets\Laravel\Controllers;

use CatLab\Assets\Laravel\Models\Asset;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Request;
use Response;

/**
 * Class AssetController
 * @package CatLab\Assets\Controllers
 */
class AssetController
{
    const QUERY_PARAM_SQUARE = 'square';
    const QUERY_PARAM_WIDTH = 'width';
    const QUERY_PARAM_HEIGHT = 'height';

    /**
     * @param $assetId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function view($assetId)
    {
        /** @var Asset $asset */
        $asset = Asset::find($assetId);
        if (!$asset) {
            abort(404, 'Asset ' . $assetId . ' not found.');
        }

        if ($asset->isImage()) {
            return $this->getImageResponse($asset);
        }

        // Do some magic here.
        return $this->getStreamResponse($asset, []);
    }

    /**
     * @return array
     */
    protected function getImageSize()
    {
        if (Request::input(self::QUERY_PARAM_SQUARE)) {
            return [
                Request::input(self::QUERY_PARAM_SQUARE),
                Request::input(self::QUERY_PARAM_SQUARE)
            ];
        }

        elseif (
            Request::input(self::QUERY_PARAM_WIDTH) &&
            Request::input(self::QUERY_PARAM_HEIGHT)
        ) {
            return [
                Request::input(self::QUERY_PARAM_WIDTH),
                Request::input(self::QUERY_PARAM_HEIGHT)
            ];
        }

        return [ null, null ];
    }

    /**
     * @param Asset $asset
     * @return \Illuminate\Http\Response
     */
    protected function getImageResponse(Asset $asset)
    {
        $response = \Illuminate\Http\Response::make(
            $asset->getResizedImage(...$this->getImageSize()),
            200,
            array_merge(
                [
                    'Content-type' => $asset->mimetype
                ],
                $this->getCacheHeaders($asset)
            )
        );

        return $response;
    }

    /**
     * @param Asset $asset
     * @return array
     */
    protected function getCacheHeaders(Asset $asset)
    {
        $expireInterval = new DateInterval('P1Y');
        $expireDate = (new DateTime())->add($expireInterval);

        return [
            'Expires' => $expireDate->format('r'),
            'Last-Modified' => $asset->created_at ? $asset->created_at->format('r') : null,
            'Cache-Control' => 'max-age=' . $this->dateIntervalToSeconds($expireInterval) . ', public'
        ];
    }

    /**
     * @param DateInterval $dateInterval
     * @return int seconds
     */
    protected function dateIntervalToSeconds($dateInterval)
    {
        $reference = new DateTimeImmutable;
        $endTime = $reference->add($dateInterval);

        return $endTime->getTimestamp() - $reference->getTimestamp();
    }

    /**
     * @param Asset $asset
     * @param string[] $forceHeaders
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    protected function getStreamResponse(Asset $asset, $forceHeaders = [])
    {
        $headers = array_merge([
            'Content-type' => $asset->mimetype
        ], $forceHeaders);

        $disk = $asset->getDisk();
        $stream = $disk->readStream($asset->path);

        $size = $disk->size($asset->path);

        // Tell the browser that we accept ranges
        $headers['Accept-Ranges'] = '0-' . $size;

        $start = 0;
        $end = $size - 1;

        $httpStatus = 200;

        // Accept ranges
        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $start;
            $c_end = $end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);

            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

            if (strpos($range, ',') !== false) {
                return \Illuminate\Http\Response::make('Requested Range Not Satisfiable', 416, $headers);
            }

            if ($range == '-') {
                $c_start = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
            }

            $c_end = ($c_end > $end) ? $end : $c_end;

            if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
                return \Illuminate\Http\Response::make('Requested Range Not Satisfiable', 416, $headers);
            }

            $start = $c_start;
            $end = $c_end;

            // Rewrite the content range header
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

            $httpStatus = 206;
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = $length;

        return \Response::stream(
            function() use ($stream, $start, $end) {
                $buffer = 102400;

                if ($start > 0) {
                    fseek($stream, $start);
                }

                $i = $start;

                while(!feof($stream) && $i <= $end) {
                    $bytesToRead = $buffer;

                    if(($i+$bytesToRead) > $end) {
                        $bytesToRead = $end - $i + 1;
                    }

                    echo fread($stream, $bytesToRead);

                    $i += $bytesToRead;
                }

                fclose($stream);
            },
            $httpStatus,
            array_merge($headers, $this->getCacheHeaders($asset))
        );
    }
}