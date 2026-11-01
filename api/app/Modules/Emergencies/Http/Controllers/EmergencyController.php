<?php

declare(strict_types=1);

namespace App\Modules\Emergencies\Http\Controllers;

use App\Models\User;
use App\Modules\Emergencies\Jobs\AlertDispatchers;
use App\Modules\Emergencies\Models\EmergencyTicket;
use App\Modules\Pets\Models\Pet;
use App\Support\PersianText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/** Emergency button and the dispatcher queue (FR-3.4). */
class EmergencyController
{
    public function contact(): JsonResponse
    {
        return response()->json(['data' => ['phone' => config('clinic.phone')]]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->filled('note')) {
            $request->merge(['note' => PersianText::normalize((string) $request->input('note'))]);
        }
        $data = $request->validate([
            'pet_id' => ['sometimes', 'nullable', 'integer'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:24,40'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:44,64'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        /** @var User $owner */
        $owner = $request->user();

        $open = EmergencyTicket::query()->where('owner_id', $owner->id)->where('status', '<>', EmergencyTicket::RESOLVED)->first();
        if ($open !== null) {
            return response()->json(['data' => $this->ownerData($open)]);
        }

        if (isset($data['pet_id'])) {
            abort_unless(Pet::query()->where('owner_id', $owner->id)->whereKey($data['pet_id'])->exists(), 404);
        }

        $ticket = EmergencyTicket::create([...$data, 'owner_id' => $owner->id]);

        // The ticket is on the staff queue already, so a queue outage must not fail the owner.
        try {
            AlertDispatchers::dispatch($ticket->id);
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json(['data' => $this->ownerData($ticket)], 201);
    }

    public function queue(): JsonResponse
    {
        Gate::authorize('handle-emergencies');

        $tickets = EmergencyTicket::query()
            ->where('status', '<>', EmergencyTicket::RESOLVED)
            ->with(['owner', 'pet'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $tickets->map(fn (EmergencyTicket $t) => $this->staffData($t))]);
    }

    public function acknowledge(Request $request, EmergencyTicket $ticket): JsonResponse
    {
        Gate::authorize('handle-emergencies');

        if ($ticket->status === EmergencyTicket::OPEN) {
            $ticket->update([
                'status' => EmergencyTicket::ACKNOWLEDGED,
                'acknowledged_by' => $request->user()?->id,
                'acknowledged_at' => now(),
            ]);
        }

        return response()->json(['data' => $this->staffData($ticket)]);
    }

    public function resolve(Request $request, EmergencyTicket $ticket): JsonResponse
    {
        Gate::authorize('handle-emergencies');

        $data = $request->validate(['resolution' => ['required', 'string', 'max:500']]);

        if ($ticket->status !== EmergencyTicket::RESOLVED) {
            $ticket->update([
                'status' => EmergencyTicket::RESOLVED,
                'resolved_by' => $request->user()?->id,
                'resolved_at' => now(),
                'resolution' => $data['resolution'],
            ]);
        }

        return response()->json(['data' => $this->staffData($ticket)]);
    }

    /** @return array<string, mixed> */
    private function ownerData(EmergencyTicket $ticket): array
    {
        return ['id' => $ticket->id, 'status' => $ticket->status, 'phone' => config('clinic.phone')];
    }

    /** @return array<string, mixed> */
    private function staffData(EmergencyTicket $ticket): array
    {
        $deadline = $ticket->created_at->copy()->addMinutes((int) config('clinic.emergency_sla_minutes'));

        return [
            'id' => $ticket->id,
            'status' => $ticket->status,
            'owner' => ['name' => $ticket->owner->name, 'mobile' => $ticket->owner->mobile],
            'pet' => $ticket->pet ? ['id' => $ticket->pet->id, 'name' => $ticket->pet->name] : null,
            'lat' => $ticket->lat,
            'lng' => $ticket->lng,
            'note' => $ticket->note,
            'resolution' => $ticket->resolution,
            'created_at' => $ticket->created_at->toIso8601ZuluString(),
            'sla_deadline' => $deadline->toIso8601ZuluString(),
            'sla_breached' => $ticket->status === EmergencyTicket::OPEN && now()->gt($deadline),
        ];
    }
}
