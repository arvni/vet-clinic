<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Http\Controllers;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Catalog\Models\Service;
use App\Modules\Locations\Models\Address;
use App\Modules\Locations\Models\ServiceZone;
use App\Modules\Pets\Models\Pet;
use App\Modules\Scheduling\SlotHolds;
use App\Support\Money;
use App\Support\PersianText;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Turns a slot hold into a booking (T030). Home visits and prepaid services
 * wait in pending_payment until paid (FR-3.3); the rest confirm at once.
 */
class BookingController
{
    private const EXCLUSION_VIOLATION = '23P01';

    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('owner_id', $this->owner($request)->id)
            ->with(['pet', 'service', 'vet', 'address'])
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $bookings->map(fn (Booking $b) => $this->data($b))]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->owner_id === $this->owner($request)->id, 404);

        return response()->json(['data' => $this->data($booking)]);
    }

    public function store(Request $request, SlotHolds $holds): JsonResponse
    {
        if ($request->filled('notes')) {
            $request->merge(['notes' => PersianText::normalize((string) $request->input('notes'))]);
        }
        $data = $request->validate([
            'hold_token' => ['required', 'uuid'],
            'pet_id' => ['required', 'integer'],
            'location' => ['required', Rule::in(Service::LOCATIONS)],
            'address_id' => ['required_if:location,home', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $owner = $this->owner($request);
        $hold = $holds->find($owner->id, $data['hold_token']);
        if ($hold === null) {
            return response()->json(['message' => __('bookings.hold_expired')], 409);
        }

        $pet = Pet::query()->where('owner_id', $owner->id)->find($data['pet_id']);
        $service = Service::query()->active()->find($hold['service_id']);
        if ($pet === null || $service === null) {
            abort(404);
        }

        if (! in_array($data['location'], $service->locations, true)) {
            throw ValidationException::withMessages(['location' => __('bookings.location_unavailable')]);
        }
        if (! $service->availableFor($pet->species->value)) {
            throw ValidationException::withMessages(['pet_id' => __('bookings.species_unavailable')]);
        }

        $address = null;
        $zone = null;
        if ($data['location'] === 'home') {
            $address = Address::query()->where('user_id', $owner->id)->find($data['address_id']);
            abort_if($address === null, 404);

            $zone = ServiceZone::findFor($address->lat, $address->lng);
            if ($zone === null) {
                throw ValidationException::withMessages(['address_id' => __('locations.outside_zones')]);
            }
        }

        $start = Carbon::parse($hold['starts_at']);
        $mustPay = $zone !== null || $service->requires_prepayment;

        try {
            $booking = Booking::create([
                'owner_id' => $owner->id,
                'pet_id' => $pet->id,
                'service_id' => $service->id,
                'vet_id' => $hold['vet_id'],
                'location' => $data['location'],
                'address_id' => $address?->id,
                'zone_id' => $zone?->id,
                'starts_at' => $start,
                'ends_at' => Carbon::parse($hold['ends_at']),
                'status' => $mustPay ? Booking::PENDING_PAYMENT : Booking::CONFIRMED,
                'service_price_rials' => $service->priceAt($start)?->rials,
                'home_visit_fee_rials' => $zone->home_visit_fee_rials ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === self::EXCLUSION_VIOLATION) {
                return response()->json(['message' => __('scheduling.slot_unavailable')], 409);
            }

            throw $e;
        }

        $holds->release($owner->id, $data['hold_token']);

        return response()->json(['data' => $this->data($booking)], 201);
    }

    private function owner(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** @return array<string, mixed> */
    private function data(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'status' => $booking->status,
            'location' => $booking->location,
            'starts_at' => $booking->starts_at->toIso8601ZuluString(),
            'ends_at' => $booking->ends_at->toIso8601ZuluString(),
            'pet' => ['id' => $booking->pet_id, 'name' => $booking->pet->name],
            'service' => ['id' => $booking->service_id, 'name' => $booking->service->name()],
            'vet_name' => $booking->vet->name,
            'address' => $booking->address?->line,
            'service_price' => $booking->service_price_rials === null ? null : Money::rials($booking->service_price_rials),
            'home_visit_fee' => Money::rials($booking->home_visit_fee_rials),
            'notes' => $booking->notes,
        ];
    }
}
