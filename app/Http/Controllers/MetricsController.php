<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Metrics\MetricsStore;
use Illuminate\Http\Response;

final class MetricsController extends Controller
{
    public function __invoke(MetricsStore $metrics): Response
    {
        return response($metrics->renderPrometheus(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
