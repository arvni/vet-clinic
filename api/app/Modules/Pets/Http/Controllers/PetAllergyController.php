<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Controllers;

use App\Modules\Pets\Http\Resources\PetAllergyResource;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Models\PetAllergy;
use App\Support\PersianText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PetAllergyController
{
    public function store(Request $request, Pet $pet): JsonResponse
    {
        Gate::authorize('update', $pet);

        if ($request->filled('substance')) {
            $request->merge(['substance' => PersianText::normalize((string) $request->input('substance'))]);
        }
        $data = $request->validate([
            'type' => ['required', Rule::in(PetAllergy::TYPES)],
            'substance' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $allergy = $pet->allergies()->create($data);

        return (new PetAllergyResource($allergy))->response()->setStatusCode(201);
    }

    public function destroy(Pet $pet, PetAllergy $allergy): JsonResponse
    {
        Gate::authorize('update', $pet);
        abort_unless($allergy->pet_id === $pet->id, 404);

        $allergy->delete();

        return response()->json(status: 204);
    }
}
