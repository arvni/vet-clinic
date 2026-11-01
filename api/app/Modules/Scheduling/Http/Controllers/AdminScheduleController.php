<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Scheduling\Models\Holiday;
use App\Modules\Scheduling\Models\ScheduleBlock;
use App\Support\PersianText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin management of vet schedules, breaks and holidays (T024). */
class AdminScheduleController
{
    public function schedule(User $vet): JsonResponse
    {
        Gate::authorize('manage-schedules');
        $this->assertVet($vet);

        return response()->json(['data' => $this->blocks($vet)]);
    }

    /** Replaces the whole weekly pattern, so the editor saves one consistent week. */
    public function replaceSchedule(Request $request, User $vet): JsonResponse
    {
        Gate::authorize('manage-schedules');
        $this->assertVet($vet);

        $data = $request->validate([
            'blocks' => ['present', 'array', 'max:100'],
            'blocks.*.weekday' => ['required', 'integer', 'between:0,6'],
            'blocks.*.kind' => ['required', Rule::in([ScheduleBlock::WORK, ScheduleBlock::BREAK])],
            'blocks.*.starts_at' => ['required', 'date_format:H:i'],
            'blocks.*.ends_at' => ['required', 'date_format:H:i', 'after:blocks.*.starts_at'],
        ]);

        $this->assertNoOverlappingWork($data['blocks']);

        DB::transaction(function () use ($vet, $data): void {
            ScheduleBlock::query()->where('user_id', $vet->id)->get()->each->delete();
            foreach ($data['blocks'] as $block) {
                ScheduleBlock::create([...$block, 'user_id' => $vet->id]);
            }
        });

        return response()->json(['data' => $this->blocks($vet)]);
    }

    public function holidays(): JsonResponse
    {
        Gate::authorize('manage-schedules');

        return response()->json(['data' => Holiday::query()
            ->whereDate('date', '>=', now()->subYear())
            ->orderBy('date')
            ->get()
            ->map(fn (Holiday $h) => $this->holidayData($h))]);
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        Gate::authorize('manage-schedules');

        $request->merge(['name_fa' => PersianText::normalize((string) $request->input('name_fa'))]);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'name_fa' => ['required', 'string', 'max:120'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $vetId = $data['user_id'] ?? null;
        $exists = Holiday::query()->whereDate('date', $data['date'])
            ->when($vetId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $vetId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['date' => __('scheduling.holiday_exists')]);
        }

        return response()->json(['data' => $this->holidayData(Holiday::create($data))], 201);
    }

    public function destroyHoliday(Holiday $holiday): Response
    {
        Gate::authorize('manage-schedules');

        $holiday->delete();

        return response()->noContent();
    }

    private function assertVet(User $vet): void
    {
        abort_unless($vet->is_staff && $vet->hasRole(Role::Vet->value), 404);
    }

    /** @param  array<int, array{weekday: int, kind: string, starts_at: string, ends_at: string}>  $blocks */
    private function assertNoOverlappingWork(array $blocks): void
    {
        $work = array_values(array_filter($blocks, fn (array $b) => $b['kind'] === ScheduleBlock::WORK));

        foreach ($work as $i => $a) {
            foreach (array_slice($work, $i + 1) as $b) {
                if ($a['weekday'] === $b['weekday'] && $a['starts_at'] < $b['ends_at'] && $b['starts_at'] < $a['ends_at']) {
                    throw ValidationException::withMessages(['blocks' => __('scheduling.blocks_overlap')]);
                }
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function blocks(User $vet): array
    {
        return ScheduleBlock::query()->where('user_id', $vet->id)->orderBy('weekday')->orderBy('starts_at')->get()
            ->map(fn (ScheduleBlock $b) => [
                'id' => $b->id,
                'weekday' => $b->weekday,
                'kind' => $b->kind,
                'starts_at' => substr($b->starts_at, 0, 5),
                'ends_at' => substr($b->ends_at, 0, 5),
            ])->all();
    }

    /** @return array<string, mixed> */
    private function holidayData(Holiday $holiday): array
    {
        return [
            'id' => $holiday->id,
            'date' => $holiday->date->toDateString(),
            'user_id' => $holiday->user_id,
            'name_fa' => $holiday->name_fa,
            'name_en' => $holiday->name_en,
        ];
    }
}
