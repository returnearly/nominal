<?php

use App\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/metrics', MetricsController::class)->name('metrics.web');
