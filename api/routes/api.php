<?php

use App\Modules\Files\FileController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\OtpAuthController;
use App\Modules\Identity\Http\Controllers\StaffAuthController;
use App\Modules\Identity\Http\Controllers\StaffController;
use App\Modules\Pets\Http\Controllers\PetAllergyController;
use App\Modules\Pets\Http\Controllers\PetController;
use App\Modules\Pets\Http\Controllers\PetDocumentController;
use App\Modules\Pets\Http\Controllers\PetTransferController;
use App\Modules\Pets\Http\Controllers\PetWeightController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('otp/request', [OtpAuthController::class, 'request']);
        Route::post('otp/verify', [OtpAuthController::class, 'verify']);
        Route::post('staff/login', [StaffAuthController::class, 'login']);
        Route::post('staff/verify', [StaffAuthController::class, 'verify']);
    });

    // Signed, short-lived file URLs (checked when issued inside an authorized response).
    Route::middleware('signed')->prefix('files')->group(function () {
        Route::get('pet-photos/{pet}', [FileController::class, 'petPhoto'])->name('files.pet-photo');
        Route::get('pet-documents/{document}', [FileController::class, 'petDocument'])->name('files.pet-document');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [MeController::class, 'logout']);
        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);

        // Pets (FR-1.3 – FR-1.7)
        Route::get('pets', [PetController::class, 'index'])->middleware('abilities:owner');
        Route::post('pets', [PetController::class, 'store'])->middleware('abilities:owner');
        Route::get('pets/{pet}', [PetController::class, 'show']);
        Route::patch('pets/{pet}', [PetController::class, 'update']);
        Route::delete('pets/{pet}', [PetController::class, 'destroy']);
        Route::post('pets/{pet}/photo', [PetController::class, 'photo']);

        Route::get('pets/{pet}/weights', [PetWeightController::class, 'index']);
        Route::post('pets/{pet}/weights', [PetWeightController::class, 'store']);

        Route::post('pets/{pet}/allergies', [PetAllergyController::class, 'store']);
        Route::delete('pets/{pet}/allergies/{allergy}', [PetAllergyController::class, 'destroy']);

        Route::get('pets/{pet}/documents', [PetDocumentController::class, 'index']);
        Route::post('pets/{pet}/documents', [PetDocumentController::class, 'store']);
        Route::post('pets/{pet}/documents/{document}', [PetDocumentController::class, 'update']);
        Route::delete('pets/{pet}/documents/{document}', [PetDocumentController::class, 'destroy']);

        // Transfers (FR-1.6)
        Route::post('pets/{pet}/transfers', [PetTransferController::class, 'store']);
        Route::get('pet-transfers/incoming', [PetTransferController::class, 'incoming'])->middleware('abilities:owner');
        Route::post('pet-transfers/{transfer}/accept', [PetTransferController::class, 'accept']);
        Route::post('pet-transfers/{transfer}/cancel', [PetTransferController::class, 'cancel']);

        Route::prefix('staff')->middleware('abilities:staff')->group(function () {
            Route::post('pet-documents/{document}/verify', [PetDocumentController::class, 'verify']);
        });

        Route::prefix('admin')->middleware('abilities:staff')->group(function () {
            Route::get('staff', [StaffController::class, 'index']);
            Route::post('staff', [StaffController::class, 'store']);
        });
    });
});
