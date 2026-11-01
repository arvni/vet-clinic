<?php

use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\OtpAuthController;
use App\Modules\Identity\Http\Controllers\StaffAuthController;
use App\Modules\Identity\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('otp/request', [OtpAuthController::class, 'request']);
        Route::post('otp/verify', [OtpAuthController::class, 'verify']);
        Route::post('staff/login', [StaffAuthController::class, 'login']);
        Route::post('staff/verify', [StaffAuthController::class, 'verify']);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [MeController::class, 'logout']);
        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);

        Route::prefix('admin')->middleware('abilities:staff')->group(function () {
            Route::get('staff', [StaffController::class, 'index']);
            Route::post('staff', [StaffController::class, 'store']);
        });
    });
});
