<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class OutboundHttp
{
    public static function json(): PendingRequest
    {
        $request = Http::acceptJson()->asJson();
        $proxy = ProxyUrl::guzzleFromConfig();

        if ($proxy === null) {
            return $request;
        }

        return $request->withOptions(['proxy' => $proxy]);
    }
}
