<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\StaffAccess;
use App\Modules\Pets\Http\Resources\PetWeightResource;
use App\Modules\Pets\Models\Pet;
use App\Support\Digits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Weight history (FR-1.4): a dated series, not a single value. */
class PetWeightController
{
    public function index(Pet $pet): AnonymousResourceCollection
    {
        Gate::authorize('view', $pet);

        return PetWeightResource::collection($pet->weights()->limit(200)->get());
    }

    public function store(Request $request, Pet $pet): JsonResponse
    {
        Gate::authorize('update', $pet);

        $request->merge(['weight_kg' => Digits::toLatin((string) $request->input('weight_kg'))]);
        $data = $request->validate([
            'weight_kg' => ['required', 'numeric', 'between:0.01,200'],
            'measured_at' => ['sometimes', 'date', 'before_or_equal:now'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $weight = $pet->weights()->create([
            'weight_grams' => (int) round((float) $data['weight_kg'] * 1000),
            'measured_at' => $data['measured_at'] ?? now(),
            'source' => StaffAccess::check($user) ? 'vet' : 'owner',
            'recorded_by' => $user->id,
        ]);

        return (new PetWeightResource($weight))->response()->setStatusCode(201);
    }
}
