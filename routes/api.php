<?php

use App\Enums\HeartbeatSignal;
use App\Http\Controllers\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/heartbeat/{token}/{signal?}', HeartbeatController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->where('signal', implode('|', array_column(HeartbeatSignal::cases(), 'value')))
    ->name('heartbeat');
