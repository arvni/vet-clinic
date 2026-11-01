<?php

declare(strict_types=1);

namespace App\Modules\Scheduling;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Temporary slot holds during checkout (FR-3.1), kept in the cache store.
 *
 * A hold claims every grid cell it covers with an atomic add, so services of
 * different lengths cannot overlap on one vet. An owner has one hold at a
 * time: taking a new one frees the old, which stops one account from sitting
 * on a whole day of slots.
 */
final class SlotHolds
{
    /**
     * @return array{token: string, expires_at: Carbon}|null null when any part of the time is taken
     */
    public function acquire(int $ownerId, int $vetId, Carbon $start, Carbon $end): ?array
    {
        $previous = Cache::get($this->ownerKey($ownerId));
        if (is_string($previous)) {
            $this->release($ownerId, $previous);
        }

        $token = (string) Str::uuid();
        $expiresAt = now()->addMinutes((int) config('scheduling.hold_minutes'));
        $claimed = [];

        foreach ($this->cells($vetId, $start, $end) as $cell) {
            if (! Cache::add($cell, $token, $expiresAt)) {
                $this->forgetCells($claimed, $token);

                return null;
            }
            $claimed[] = $cell;
        }

        Cache::put($this->holdKey($token), ['owner_id' => $ownerId, 'cells' => $claimed], $expiresAt);
        Cache::put($this->ownerKey($ownerId), $token, $expiresAt);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    /** Frees a hold; false when it has expired or belongs to another owner. */
    public function release(int $ownerId, string $token): bool
    {
        $hold = Cache::get($this->holdKey($token));
        if (! is_array($hold) || $hold['owner_id'] !== $ownerId) {
            return false;
        }

        $this->forgetCells($hold['cells'], $token);
        Cache::forget($this->holdKey($token));
        Cache::forget($this->ownerKey($ownerId));

        return true;
    }

    public function isHeld(int $vetId, Carbon $start, Carbon $end): bool
    {
        foreach ($this->cells($vetId, $start, $end) as $cell) {
            if (Cache::has($cell)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $cells */
    private function forgetCells(array $cells, string $token): void
    {
        foreach ($cells as $cell) {
            if (Cache::get($cell) === $token) {
                Cache::forget($cell);
            }
        }
    }

    /** @return list<string> */
    private function cells(int $vetId, Carbon $start, Carbon $end): array
    {
        $step = (int) config('scheduling.step_minutes') * 60;
        $cells = [];

        for ($t = $start->getTimestamp(); $t < $end->getTimestamp(); $t += $step) {
            $cells[] = "slot-cell:{$vetId}:{$t}";
        }

        return $cells;
    }

    private function holdKey(string $token): string
    {
        return "slot-hold:{$token}";
    }

    private function ownerKey(int $ownerId): string
    {
        return "slot-hold-owner:{$ownerId}";
    }
}
