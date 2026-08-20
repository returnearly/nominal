<?php

use App\Http\Controllers\PushHeartbeatController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/push/{token}', PushHeartbeatController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('push.heartbeat');
