<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ServiceResource;
use App\Modules\Catalog\Http\Resources\VaccineResource;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\Vaccine;
use App\Modules\Pets\Models\Breed;
use App\Modules\Pets\Species;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Public, read-only catalog for the owner app. */
class CatalogController
{
    public function species(): JsonResponse
    {
        return response()->json(['data' => array_map(
            fn (Species $s) => ['value' => $s->value, 'label' => $s->label()],
            Species::cases(),
        )]);
    }

    public function breeds(Request $request): JsonResponse
    {
        $data = $request->validate(['species' => ['required', Rule::in(Species::values())]]);
        $locale = app()->getLocale();

        $breeds = Breed::query()->where('species', $data['species'])->orderBy('name_'.$locale)->get()
            ->map(fn (Breed $b) => ['id' => $b->id, 'name' => $b->name()]);

        return response()->json(['data' => $breeds]);
    }

    public function services(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'species' => ['sometimes', Rule::in(Species::values())],
            'location' => ['sometimes', Rule::in(Service::LOCATIONS)],
        ]);

        $services = Service::query()->active()
            ->with('prices')
            ->when($data['location'] ?? null, fn ($q, $loc) => $q->whereJsonContains('locations', $loc))
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->filter(fn (Service $s) => $s->availableFor($data['species'] ?? null))
            ->values();

        return ServiceResource::collection($services);
    }

    public function vaccines(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'species' => ['required', Rule::in(Species::values())],
            'age_weeks' => ['sometimes', 'integer', 'min:0'],
        ]);

        $vaccines = Vaccine::query()->active()->forSpecies($data['species'])->orderBy('name_fa')->get()
            ->filter(fn (Vaccine $v) => $v->suitableFor($data['species'], isset($data['age_weeks']) ? (int) $data['age_weeks'] : null))
            ->values();

        return VaccineResource::collection($vaccines);
    }
}
