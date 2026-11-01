<?php

declare(strict_types=1);

namespace App\Modules\Bookings;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\Setting;

/** Admin-set cancellation and reschedule windows (FR-3.5). */
final class BookingPolicy
{
    private const KEY = 'booking_policy';

    private const DEFAULTS = ['cancel_hours' => 24, 'reschedule_hours' => 24];

    /** @return array{cancel_hours: int, reschedule_hours: int} */
    public function windows(): array
    {
        $setting = Setting::query()->where('key', self::KEY)->first();
        $stored = $setting === null ? [] : $setting->value;

        return [
            'cancel_hours' => (int) ($stored['cancel_hours'] ?? self::DEFAULTS['cancel_hours']),
            'reschedule_hours' => (int) ($stored['reschedule_hours'] ?? self::DEFAULTS['reschedule_hours']),
        ];
    }

    /** @param  array{cancel_hours: int, reschedule_hours: int}  $windows */
    public function save(array $windows): void
    {
        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $windows]);
    }

    public function canCancel(Booking $booking): bool
    {
        return $this->isOpen($booking) && now()->lte($booking->starts_at->copy()->subHours($this->windows()['cancel_hours']));
    }

    public function canReschedule(Booking $booking): bool
    {
        return $this->isOpen($booking)
            && $booking->dispatched_at === null
            && now()->lte($booking->starts_at->copy()->subHours($this->windows()['reschedule_hours']));
    }

    /** The home-visit fee comes back only if it was paid and nobody has left yet. */
    public function refundDue(Booking $booking): int
    {
        return $booking->status === Booking::CONFIRMED && $booking->dispatched_at === null
            ? $booking->home_visit_fee_rials
            : 0;
    }

    private function isOpen(Booking $booking): bool
    {
        return $booking->status !== Booking::CANCELLED;
    }
}
