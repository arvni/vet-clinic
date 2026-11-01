<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Modules\Locations\Maps\MapProvider;
use App\Modules\Locations\Maps\MapUnavailableException;
use App\Modules\Locations\Models\ServiceZone;
use App\Support\Digits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Zone lookup for owners (FR-3.2), zone editing for admins (FR-5.2), map proxy. */
class ZoneController
{
    private const LAT = ['required', 'numeric', 'between:24,40'];

    private const LNG = ['required', 'numeric', 'between:44,64'];

    /** @return array<string, mixed> */
    public static function summary(ServiceZone $zone): array
    {
        return ['id' => $zone->id, 'name' => $zone->name(), 'home_visit_fee' => $zone->fee()];
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['lat' => self::LAT, 'lng' => self::LNG]);
        $zone = ServiceZone::findFor((float) $data['lat'], (float) $data['lng']);

        return response()->json(['data' => [
            'home_available' => $zone !== null,
            'zone' => $zone ? self::summary($zone) : null,
            'message' => $zone ? null : __('locations.outside_zones'),
        ]]);
    }

    public function reverse(Request $request, MapProvider $maps): JsonResponse
    {
        $data = $request->validate(['lat' => self::LAT, 'lng' => self::LNG]);

        return $this->fromProvider(fn () => ['address' => $maps->reverse((float) $data['lat'], (float) $data['lng'])]);
    }

    public function search(Request $request, MapProvider $maps): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        return $this->fromProvider(fn () => $maps->search(
            $data['q'],
            (float) config('maps.center.lat'),
            (float) config('maps.center.lng'),
        ));
    }

    /** @param  callable(): array<mixed>  $call */
    private function fromProvider(callable $call): JsonResponse
    {
        try {
            return response()->json(['data' => $call()]);
        } catch (MapUnavailableException $e) {
            report($e);

            return response()->json(['message' => __('locations.map_unavailable')], 503);
        }
    }

    public function index(): JsonResponse
    {
        Gate::authorize('manage-zones');

        return response()->json(['data' => ServiceZone::query()->orderBy('sort')->orderBy('id')->get()
            ->map(fn (ServiceZone $z) => $this->adminData($z))]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('manage-zones');

        return response()->json(['data' => $this->adminData(ServiceZone::create($this->validated($request, true)))], 201);
    }

    public function update(Request $request, ServiceZone $zone): JsonResponse
    {
        Gate::authorize('manage-zones');

        $zone->update($this->validated($request, false));

        return response()->json(['data' => $this->adminData($zone)]);
    }

    public function destroy(ServiceZone $zone): Response
    {
        Gate::authorize('manage-zones');

        $zone->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        if ($request->filled('home_visit_fee_toman')) {
            $request->merge(['home_visit_fee_toman' => Digits::toLatin(str_replace([',', '٬', ' '], '', (string) $request->input('home_visit_fee_toman')))]);
        }

        $data = validator($request->all(), [
            'name_fa' => [$required, 'string', 'max:120'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:120'],
            'polygon' => [$required, 'array', 'min:3', 'max:500'],
            'polygon.*' => ['array', 'size:2'],
            'polygon.*.0' => ['required', 'numeric', 'between:24,40'],
            'polygon.*.1' => ['required', 'numeric', 'between:44,64'],
            'home_visit_fee_toman' => [$required, 'integer', 'min:0'],
            'sort' => ['sometimes', 'integer', 'between:0,1000'],
            'active' => ['sometimes', 'boolean'],
        ])->validate();

        if (isset($data['home_visit_fee_toman'])) {
            $data['home_visit_fee_rials'] = (int) $data['home_visit_fee_toman'] * 10;
            unset($data['home_visit_fee_toman']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function adminData(ServiceZone $zone): array
    {
        return [
            'id' => $zone->id,
            'name_fa' => $zone->name_fa,
            'name_en' => $zone->name_en,
            'polygon' => $zone->polygon,
            'home_visit_fee' => $zone->fee(),
            'sort' => $zone->sort,
            'active' => $zone->active,
        ];
    }
}
