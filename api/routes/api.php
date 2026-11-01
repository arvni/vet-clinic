<?php

use App\Modules\Bookings\Http\Controllers\BookingController;
use App\Modules\Catalog\Http\Controllers\AdminCatalogController;
use App\Modules\Catalog\Http\Controllers\CatalogController;
use App\Modules\Files\FileController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\OtpAuthController;
use App\Modules\Identity\Http\Controllers\StaffAuthController;
use App\Modules\Identity\Http\Controllers\StaffController;
use App\Modules\Locations\Http\Controllers\AddressController;
use App\Modules\Locations\Http\Controllers\ZoneController;
use App\Modules\Pets\Http\Controllers\PetAllergyController;
use App\Modules\Pets\Http\Controllers\PetController;
use App\Modules\Pets\Http\Controllers\PetDocumentController;
use App\Modules\Pets\Http\Controllers\PetTransferController;
use App\Modules\Pets\Http\Controllers\PetWeightController;
use App\Modules\Scheduling\Http\Controllers\AdminScheduleController;
use App\Modules\Scheduling\Http\Controllers\SlotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('otp/request', [OtpAuthController::class, 'request']);
        Route::post('otp/verify', [OtpAuthController::class, 'verify']);
        Route::post('staff/login', [StaffAuthController::class, 'login']);
        Route::post('staff/verify', [StaffAuthController::class, 'verify']);
    });

    // Public catalog (FR-2, FR-4.1)
    Route::prefix('catalog')->middleware('throttle:60,1')->group(function () {
        Route::get('species', [CatalogController::class, 'species']);
        Route::get('breeds', [CatalogController::class, 'breeds']);
        Route::get('services', [CatalogController::class, 'services']);
        Route::get('vaccines', [CatalogController::class, 'vaccines']);
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

        // Slot search and checkout holds (FR-3.1)
        Route::middleware(['abilities:owner', 'throttle:60,1'])->group(function () {
            Route::get('slots', [SlotController::class, 'index']);
            Route::post('slot-holds', [SlotController::class, 'hold']);
            Route::delete('slot-holds/{token}', [SlotController::class, 'release'])->whereUuid('token');
        });

        // Bookings (T030)
        Route::middleware(['abilities:owner', 'throttle:60,1'])->group(function () {
            Route::get('bookings', [BookingController::class, 'index']);
            Route::post('bookings', [BookingController::class, 'store']);
            Route::get('bookings/{booking}', [BookingController::class, 'show']);
        });

        // Address book, zone lookup and map proxy (FR-3.2)
        Route::middleware(['abilities:owner', 'throttle:60,1'])->group(function () {
            Route::get('addresses', [AddressController::class, 'index']);
            Route::post('addresses', [AddressController::class, 'store']);
            Route::patch('addresses/{address}', [AddressController::class, 'update']);
            Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
            Route::get('zones/lookup', [ZoneController::class, 'lookup']);
            Route::get('maps/reverse', [ZoneController::class, 'reverse']);
            Route::get('maps/search', [ZoneController::class, 'search']);
        });

        Route::prefix('staff')->middleware('abilities:staff')->group(function () {
            Route::post('pet-documents/{document}/verify', [PetDocumentController::class, 'verify']);
        });

        Route::prefix('admin')->middleware('abilities:staff')->group(function () {
            Route::get('staff', [StaffController::class, 'index']);
            Route::post('staff', [StaffController::class, 'store']);

            Route::get('services', [AdminCatalogController::class, 'services']);
            Route::post('services', [AdminCatalogController::class, 'storeService']);
            Route::patch('services/{service}', [AdminCatalogController::class, 'updateService']);
            Route::post('services/{service}/prices', [AdminCatalogController::class, 'storeServicePrice']);
            Route::get('vaccines', [AdminCatalogController::class, 'vaccines']);
            Route::post('vaccines', [AdminCatalogController::class, 'storeVaccine']);
            Route::patch('vaccines/{vaccine}', [AdminCatalogController::class, 'updateVaccine']);

            Route::get('vets/{vet}/schedule', [AdminScheduleController::class, 'schedule']);
            Route::put('vets/{vet}/schedule', [AdminScheduleController::class, 'replaceSchedule']);
            Route::get('holidays', [AdminScheduleController::class, 'holidays']);
            Route::post('holidays', [AdminScheduleController::class, 'storeHoliday']);
            Route::delete('holidays/{holiday}', [AdminScheduleController::class, 'destroyHoliday']);

            Route::get('zones', [ZoneController::class, 'index']);
            Route::post('zones', [ZoneController::class, 'store']);
            Route::patch('zones/{zone}', [ZoneController::class, 'update']);
            Route::delete('zones/{zone}', [ZoneController::class, 'destroy']);
        });
    });
});
