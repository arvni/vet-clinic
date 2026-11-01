<?php

declare(strict_types=1);

namespace App\Modules\Locations\Http\Controllers;

use App\Models\User;
use App\Modules\Locations\Models\Address;
use App\Modules\Locations\Models\ServiceZone;
use App\Support\PersianText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Owner address book (T027); each address reports whether home visits reach it. */
class AddressController
{
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::query()->where('user_id', $this->owner($request)->id)->latest('id')->get();

        return response()->json(['data' => $addresses->map(fn (Address $a) => $this->data($a))]);
    }

    public function store(Request $request): JsonResponse
    {
        $address = Address::create([...$this->validated($request, true), 'user_id' => $this->owner($request)->id]);

        return response()->json(['data' => $this->data($address)], 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        $this->assertOwned($request, $address);
        $address->update($this->validated($request, false));

        return response()->json(['data' => $this->data($address)]);
    }

    public function destroy(Request $request, Address $address): Response
    {
        $this->assertOwned($request, $address);
        $address->delete();

        return response()->noContent();
    }

    /** Owners cannot learn whether another owner's address id exists. */
    private function assertOwned(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $this->owner($request)->id, 404);
    }

    private function owner(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        foreach (['label', 'line', 'details'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => PersianText::normalize((string) $request->input($field))]);
            }
        }

        return $request->validate([
            'label' => [$required, 'string', 'max:60'],
            'line' => [$required, 'string', 'max:500'],
            'details' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lat' => [$required, 'numeric', 'between:24,40'],
            'lng' => [$required, 'numeric', 'between:44,64'],
        ]);
    }

    /** @return array<string, mixed> */
    private function data(Address $address): array
    {
        $zone = ServiceZone::findFor($address->lat, $address->lng);

        return [
            'id' => $address->id,
            'label' => $address->label,
            'line' => $address->line,
            'details' => $address->details,
            'lat' => $address->lat,
            'lng' => $address->lng,
            'zone' => $zone ? ZoneController::summary($zone) : null,
        ];
    }
}
