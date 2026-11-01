<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Scheduling\SlotHolds;
use App\Modules\Scheduling\SlotSearch;
use App\Support\JalaliDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/** Slot search and checkout holds for owners (FR-3.1). */
class SlotController
{
    public function index(Request $request, SlotSearch $search): JsonResponse
    {
        $today = now(JalaliDate::TIMEZONE)->toDateString();
        $last = now(JalaliDate::TIMEZONE)->addDays((int) config('scheduling.horizon_days'))->toDateString();

        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$today}", "before_or_equal:{$last}"],
            'vet_id' => ['sometimes', 'integer'],
        ]);

        $service = Service::query()->active()->findOrFail($data['service_id']);
        $slots = $search->forDate($service, $data['date'], isset($data['vet_id']) ? (int) $data['vet_id'] : null);

        return response()->json(['data' => array_map(fn (array $slot) => [
            'vet_id' => $slot['vet_id'],
            'vet_name' => $slot['vet_name'],
            'starts_at' => $slot['starts_at']->toIso8601ZuluString(),
            'ends_at' => $slot['ends_at']->toIso8601ZuluString(),
        ], $slots)]);
    }

    public function hold(Request $request, SlotSearch $search, SlotHolds $holds): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'vet_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $service = Service::query()->active()->findOrFail($data['service_id']);
        $start = Carbon::parse($data['starts_at'])->utc();

        $holds->releaseCurrent($user->id);

        if (! $search->isOffered($service, (int) $data['vet_id'], $start)) {
            return response()->json(['message' => __('scheduling.slot_unavailable')], 409);
        }

        $hold = $holds->acquire($user->id, $service->id, (int) $data['vet_id'], $start, $start->copy()->addMinutes($service->duration_minutes));
        if ($hold === null) {
            return response()->json(['message' => __('scheduling.slot_unavailable')], 409);
        }

        return response()->json(['data' => [
            'token' => $hold['token'],
            'expires_at' => $hold['expires_at']->toIso8601ZuluString(),
        ]], 201);
    }

    public function release(Request $request, SlotHolds $holds, string $token): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($holds->release($user->id, $token), 404);

        return response()->noContent();
    }
}
