<?php

declare(strict_types=1);

namespace App\Modules\Scheduling;

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Role;
use App\Modules\Scheduling\Models\Holiday;
use App\Modules\Scheduling\Models\ScheduleBlock;
use App\Support\JalaliDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Free start times for a service on one clinic-local date (FR-3.1): inside a
 * work block, clear of breaks and closed days, in the future and not held.
 */
final class SlotSearch
{
    public function __construct(private readonly SlotHolds $holds) {}

    /**
     * @return list<array{vet_id: int, vet_name: string|null, starts_at: Carbon, ends_at: Carbon}>
     */
    public function forDate(Service $service, string $date, ?int $vetId = null): array
    {
        $slots = [];
        foreach ($this->vets($vetId) as $vet) {
            foreach ($this->startsFor($vet->id, $service->duration_minutes, $date) as $start) {
                $slots[] = [
                    'vet_id' => $vet->id,
                    'vet_name' => $vet->name,
                    'starts_at' => $start,
                    'ends_at' => $start->copy()->addMinutes($service->duration_minutes),
                ];
            }
        }

        usort($slots, fn (array $a, array $b) => [$a['starts_at'], $a['vet_id']] <=> [$b['starts_at'], $b['vet_id']]);

        return $slots;
    }

    /** True when $start is a slot a new hold may take; the hold itself settles any race. */
    public function isOffered(Service $service, int $vetId, Carbon $start): bool
    {
        $date = $start->copy()->setTimezone(JalaliDate::TIMEZONE)->toDateString();

        foreach ($this->startsFor($vetId, $service->duration_minutes, $date) as $candidate) {
            if ($candidate->equalTo($start)) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, User> */
    private function vets(?int $vetId): Collection
    {
        return User::query()
            ->role(Role::Vet->value)
            ->where('is_staff', true)
            ->where('status', User::STATUS_ACTIVE)
            ->when($vetId !== null, fn ($query) => $query->whereKey($vetId))
            ->orderBy('id')
            ->get();
    }

    /** @return list<Carbon> */
    private function startsFor(int $vetId, int $duration, string $date): array
    {
        $closed = Holiday::query()
            ->whereDate('date', $date)
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $vetId))
            ->exists();
        if ($closed) {
            return [];
        }

        $weekday = Carbon::parse($date, JalaliDate::TIMEZONE)->dayOfWeek;
        $blocks = ScheduleBlock::query()->where('user_id', $vetId)->where('weekday', $weekday)->orderBy('starts_at')->get();
        $breaks = $blocks->where('kind', ScheduleBlock::BREAK);
        $step = (int) config('scheduling.step_minutes');

        $starts = [];
        foreach ($blocks->where('kind', ScheduleBlock::WORK) as $work) {
            $workEnd = $this->at($date, $work->ends_at);

            for ($start = $this->at($date, $work->starts_at); $start->copy()->addMinutes($duration)->lte($workEnd); $start = $start->copy()->addMinutes($step)) {
                $end = $start->copy()->addMinutes($duration);

                if ($start->isFuture() && ! $this->overlapsBreak($breaks, $date, $start, $end) && ! $this->holds->isHeld($vetId, $start, $end)) {
                    $starts[] = $start->copy()->utc();
                }
            }
        }

        return $starts;
    }

    /** @param  Collection<int, ScheduleBlock>  $breaks */
    private function overlapsBreak(Collection $breaks, string $date, Carbon $start, Carbon $end): bool
    {
        return $breaks->contains(
            fn (ScheduleBlock $b) => $start->lt($this->at($date, $b->ends_at)) && $end->gt($this->at($date, $b->starts_at))
        );
    }

    private function at(string $date, string $time): Carbon
    {
        return Carbon::parse($date.' '.$time, JalaliDate::TIMEZONE);
    }
}
