<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Models\User;
use App\Modules\Catalog\Http\Requests\ServiceRequest;
use App\Modules\Catalog\Http\Requests\VaccineRequest;
use App\Modules\Catalog\Http\Resources\ServiceResource;
use App\Modules\Catalog\Http\Resources\VaccineResource;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\Vaccine;
use App\Support\Digits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Admin catalog management (FR-2.1–2.3, FR-4.1). Services are added and
 * changed without a deploy; disabling hides them from new bookings only.
 */
class AdminCatalogController
{
    public function services(): AnonymousResourceCollection
    {
        Gate::authorize('manage-catalog');

        return ServiceResource::collection(Service::query()->with('prices')->orderBy('sort')->orderBy('id')->get());
    }

    public function storeService(ServiceRequest $request): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $service = DB::transaction(function () use ($request): Service {
            $service = Service::create($request->serviceAttributes());
            if ($request->filled('price_toman')) {
                $this->addPrice($service, (int) $request->validated('price_toman') * 10, now(), $request->user());
            }

            return $service;
        });

        return (new ServiceResource($service->load('prices')))->response()->setStatusCode(201);
    }

    public function updateService(ServiceRequest $request, Service $service): ServiceResource
    {
        Gate::authorize('manage-catalog');

        $service->update($request->serviceAttributes());

        return new ServiceResource($service->load('prices'));
    }

    /** New price row with an effective date; history is never edited (FR-2.3). */
    public function storeServicePrice(Request $request, Service $service): ServiceResource
    {
        Gate::authorize('manage-catalog');

        $request->merge(['price_toman' => Digits::toLatin(str_replace([',', '٬', ' '], '', (string) $request->input('price_toman')))]);
        $data = $request->validate([
            'price_toman' => ['required', 'integer', 'min:0'],
            'effective_from' => ['sometimes', 'date', 'after_or_equal:'.now()->subMinute()->toIso8601String()],
        ]);

        $effective = isset($data['effective_from']) ? Carbon::parse($data['effective_from'])->utc() : now();
        if ($service->prices()->where('effective_from', $effective)->exists()) {
            throw ValidationException::withMessages(['effective_from' => __('validation.unique', ['attribute' => 'effective_from'])]);
        }

        $this->addPrice($service, (int) $data['price_toman'] * 10, $effective, $request->user());

        return new ServiceResource($service->load('prices'));
    }

    public function vaccines(): AnonymousResourceCollection
    {
        Gate::authorize('manage-catalog');

        return VaccineResource::collection(Vaccine::query()->orderBy('name_fa')->get());
    }

    public function storeVaccine(VaccineRequest $request): JsonResponse
    {
        Gate::authorize('manage-catalog');

        $vaccine = Vaccine::create($request->vaccineAttributes());

        return (new VaccineResource($vaccine))->response()->setStatusCode(201);
    }

    public function updateVaccine(VaccineRequest $request, Vaccine $vaccine): VaccineResource
    {
        Gate::authorize('manage-catalog');

        $vaccine->update($request->vaccineAttributes());

        return new VaccineResource($vaccine);
    }

    private function addPrice(Service $service, int $rials, \DateTimeInterface $effectiveFrom, ?User $by): void
    {
        $service->prices()->create([
            'price_rials' => $rials,
            'effective_from' => $effectiveFrom,
            'created_by' => $by?->id,
        ]);
    }
}
