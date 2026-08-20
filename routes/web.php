<?php

use App\Http\Controllers\MetricsController;
use App\Http\Controllers\StatusPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StatusPageController::class, 'home']);

Route::get('/status/{slug}', [StatusPageController::class, 'showBySlug'])->name('status.show');
Route::post('/status/{slug}/unlock', [StatusPageController::class, 'unlockBySlug'])->name('status.unlock');
Route::get('/status/{slug}/incidents/{incident}', [StatusPageController::class, 'incidentBySlug'])->name('status.incident');

Route::get('/incidents/{incident}', [StatusPageController::class, 'incidentOnDomain'])->name('status.domain.incident');
Route::post('/unlock', [StatusPageController::class, 'unlockOnDomain'])->name('status.domain.unlock');

Route::get('/metrics', MetricsController::class)->name('metrics.web');
