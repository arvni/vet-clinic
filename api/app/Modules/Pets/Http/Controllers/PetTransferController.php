<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Controllers;

use App\Models\User;
use App\Modules\Audit\Audit;
use App\Modules\Pets\Http\Resources\PetTransferResource;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Models\PetTransfer;
use App\Support\IranMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pet transfer between accounts (FR-1.6). The current owner starts it from
 * their signed-in account; the new owner accepts from an account signed in
 * with the recipient mobile (verified by OTP), so both mobiles confirm.
 * History moves with the pet because records hang off the pet, not the owner.
 */
class PetTransferController
{
    public function store(Request $request, Pet $pet): JsonResponse
    {
        Gate::authorize('transfer', $pet);

        $mobile = IranMobile::normalize((string) $request->input('to_mobile'));
        $request->merge(['to_mobile' => $mobile ?? $request->input('to_mobile')]);
        $request->validate(['to_mobile' => ['required', 'string', 'regex:/^\+989\d{9}$/']], [
            'to_mobile.regex' => __('auth.invalid_mobile'),
        ]);

        /** @var User $user */
        $user = $request->user();
        if ($mobile === $user->mobile) {
            throw ValidationException::withMessages(['to_mobile' => __('pets.transfer.self')]);
        }

        $transfer = DB::transaction(function () use ($pet, $user, $mobile): PetTransfer {
            // Lazily close expired requests so the one-pending index does not block a new one.
            $pet->hasMany(PetTransfer::class)->where('status', PetTransfer::PENDING)
                ->where('expires_at', '<=', now())->update(['status' => PetTransfer::CANCELLED]);

            if ($pet->hasMany(PetTransfer::class)->where('status', PetTransfer::PENDING)->exists()) {
                throw ValidationException::withMessages(['to_mobile' => __('pets.transfer.pending_exists')]);
            }

            $transfer = new PetTransfer([
                'to_mobile' => $mobile,
                'from_user_id' => $user->id,
                'expires_at' => now()->addDays(PetTransfer::TTL_DAYS),
            ]);
            $transfer->pet()->associate($pet);
            $transfer->save();

            return $transfer;
        });

        Audit::record('pet.transfer_requested', $pet, after: ['to_mobile' => $mobile]);

        return (new PetTransferResource($transfer->load(['pet', 'fromUser'])))->response()->setStatusCode(201);
    }

    public function incoming(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return PetTransferResource::collection(
            PetTransfer::query()->open()->where('to_mobile', $user->mobile)->with(['pet', 'fromUser'])->get()
        );
    }

    public function accept(Request $request, PetTransfer $transfer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($transfer->to_mobile === $user->mobile && $user->tokenCan('owner'), 404);

        DB::transaction(function () use ($transfer, $user): void {
            $locked = PetTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['transfer' => __('pets.transfer.not_open')]);
            }

            $pet = Pet::query()->lockForUpdate()->findOrFail($locked->pet_id);
            $previousOwner = $pet->owner_id;
            $pet->owner_id = $user->id;
            $pet->save();

            $locked->status = PetTransfer::ACCEPTED;
            $locked->to_user_id = $user->id;
            $locked->completed_at = now();
            $locked->save();

            Audit::record('pet.transferred', $pet, ['owner_id' => $previousOwner], ['owner_id' => $user->id]);
        });

        return response()->json(['message' => 'ok']);
    }

    public function cancel(Request $request, PetTransfer $transfer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isParty = $transfer->from_user_id === $user->id || $transfer->to_mobile === $user->mobile;
        abort_unless($isParty, 404);

        if ($transfer->status === PetTransfer::PENDING) {
            $transfer->status = PetTransfer::CANCELLED;
            $transfer->completed_at = now();
            $transfer->save();
        }

        return response()->json(status: 204);
    }
}
