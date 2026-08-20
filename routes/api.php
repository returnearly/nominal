<?php

use App\Http\Controllers\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/heartbeat/{token}', HeartbeatController::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('heartbeat');
