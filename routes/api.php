<?php

use App\Enums\HeartbeatSignal;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/heartbeat/{token}/{signal?}', HeartbeatController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->where('signal', implode('|', array_column(HeartbeatSignal::cases(), 'value')))
    ->name('heartbeat');

Route::get('/badges/{monitor}/status.svg', [BadgeController::class, 'statusSvg'])
    ->name('badges.status.svg');
Route::get('/badges/{monitor}/status.json', [BadgeController::class, 'statusJson'])
    ->name('badges.status.json');

Route::get('/badges/{monitor}/{kind}/{period}/badge.svg', [BadgeController::class, 'windowedSvg'])
    ->where('kind', 'uptime|latency')
    ->where('period', '[0-9]+[hd]')
    ->name('badges.windowed.svg');
Route::get('/badges/{monitor}/{kind}/{period}/badge.json', [BadgeController::class, 'windowedJson'])
    ->where('kind', 'uptime|latency')
    ->where('period', '[0-9]+[hd]')
    ->name('badges.windowed.json');
Route::get('/badges/{monitor}/{kind}/{period}', [BadgeController::class, 'windowedJson'])
    ->where('kind', 'uptime|latency')
    ->where('period', '[0-9]+[hd]')
    ->name('badges.windowed.raw');

Route::get('/badges/{monitor}/{kind}/badge.svg', [BadgeController::class, 'windowedSvg'])
    ->where('kind', 'uptime|latency')
    ->name('badges.windowed.default.svg');
Route::get('/badges/{monitor}/{kind}/badge.json', [BadgeController::class, 'windowedJson'])
    ->where('kind', 'uptime|latency')
    ->name('badges.windowed.default.json');
Route::get('/badges/{monitor}/{kind}', [BadgeController::class, 'windowedJson'])
    ->where('kind', 'uptime|latency')
    ->name('badges.windowed.default.raw');
