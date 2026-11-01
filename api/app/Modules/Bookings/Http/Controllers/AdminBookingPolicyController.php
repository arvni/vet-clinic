<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Http\Controllers;

use App\Modules\Bookings\BookingPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminBookingPolicyController
{
    public function show(BookingPolicy $policy): JsonResponse
    {
        Gate::authorize('manage-schedules');

        return response()->json(['data' => $policy->windows()]);
    }

    public function update(Request $request, BookingPolicy $policy): JsonResponse
    {
        Gate::authorize('manage-schedules');

        $data = $request->validate([
            'cancel_hours' => ['required', 'integer', 'between:0,720'],
            'reschedule_hours' => ['required', 'integer', 'between:0,720'],
        ]);
        $policy->save(['cancel_hours' => (int) $data['cancel_hours'], 'reschedule_hours' => (int) $data['reschedule_hours']]);

        return response()->json(['data' => $policy->windows()]);
    }
}
