<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Controllers;

use App\Models\User;
use App\Modules\Files\FileStorage;
use App\Modules\Pets\Http\Requests\PetRequest;
use App\Modules\Pets\Http\Resources\PetResource;
use App\Modules\Pets\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Owner pet profiles (FR-1.3, FR-1.4). */
class PetController
{
    public function __construct(private readonly FileStorage $files) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $pets = Pet::query()
            ->where('owner_id', $user->id)
            ->with(['breed', 'latestWeight'])
            ->orderBy('name')
            ->get();

        return PetResource::collection($pets);
    }

    public function store(PetRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $pet = DB::transaction(function () use ($request, $user): Pet {
            $pet = new Pet($request->petAttributes());
            $pet->owner_id = $user->id;
            $pet->save();

            if ($request->filled('weight_kg')) {
                $pet->weights()->create([
                    'weight_grams' => (int) round((float) $request->validated('weight_kg') * 1000),
                    'measured_at' => now(),
                    'source' => 'owner',
                    'recorded_by' => $user->id,
                ]);
            }

            return $pet;
        });

        return (new PetResource($pet->load(['breed', 'latestWeight'])))->response()->setStatusCode(201);
    }

    public function show(Pet $pet): PetResource
    {
        Gate::authorize('view', $pet);

        return new PetResource($pet->load(['breed', 'latestWeight', 'allergies', 'documents']));
    }

    public function update(PetRequest $request, Pet $pet): PetResource
    {
        Gate::authorize('update', $pet);

        $pet->update($request->petAttributes());

        return new PetResource($pet->load(['breed', 'latestWeight']));
    }

    public function destroy(Pet $pet): JsonResponse
    {
        Gate::authorize('delete', $pet);

        // Soft delete keeps medical and invoice history intact.
        $pet->delete();

        return response()->json(status: 204);
    }

    public function photo(Request $request, Pet $pet): PetResource
    {
        Gate::authorize('update', $pet);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('files.max_photo_kb')],
        ]);

        $old = $pet->photo_path;
        $pet->photo_path = $this->files->store($request->file('photo'), 'pet-photos');
        $pet->save();
        $this->files->delete($old);

        return new PetResource($pet->load(['breed', 'latestWeight']));
    }
}
