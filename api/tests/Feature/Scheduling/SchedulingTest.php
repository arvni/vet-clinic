<?php

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Role;
use App\Modules\Scheduling\Models\Holiday;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Sunday 2026-11-08, 08:00 Tehran.
    $this->travelTo(Carbon::parse('2026-11-08 08:00', 'Asia/Tehran'));

    $this->adminToken = User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    $this->vet = User::factory()->staff(Role::Vet)->create();
    $this->owner = User::factory()->create();
    $this->ownerToken = $this->owner->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->service = Service::create([
        'code' => 'visit30', 'name_fa' => 'ویزیت', 'category' => 'visit',
        'duration_minutes' => 30, 'locations' => ['clinic'],
    ]);

    // Sundays 09:00–12:00 with a break at 10:00–10:30.
    $this->withToken($this->adminToken)->putJson("/api/v1/admin/vets/{$this->vet->id}/schedule", ['blocks' => [
        ['weekday' => 0, 'kind' => 'work', 'starts_at' => '09:00', 'ends_at' => '12:00'],
        ['weekday' => 0, 'kind' => 'break', 'starts_at' => '10:00', 'ends_at' => '10:30'],
    ]])->assertOk();
});

function slots($test, string $date = '2026-11-08'): array
{
    return $test->withToken($test->ownerToken)
        ->getJson("/api/v1/slots?service_id={$test->service->id}&date={$date}")
        ->assertOk()->json('data');
}

function holdAt($test, string $tehranTime, ?string $token = null)
{
    return $test->withToken($token ?? $test->ownerToken)->postJson('/api/v1/slot-holds', [
        'service_id' => $test->service->id,
        'vet_id' => $test->vet->id,
        'starts_at' => Carbon::parse('2026-11-08 '.$tehranTime, 'Asia/Tehran')->toIso8601String(),
    ]);
}

function startsLocal(array $slots): array
{
    return array_map(fn ($s) => Carbon::parse($s['starts_at'])->setTimezone('Asia/Tehran')->format('H:i'), $slots);
}

it('offers duration-sized slots on the grid, clear of breaks', function () {
    expect(startsLocal(slots($this)))->toBe([
        '09:00', '09:15', '09:30', '10:30', '10:45', '11:00', '11:15', '11:30',
    ]);
});

it('hides past times today', function () {
    $this->travelTo(Carbon::parse('2026-11-08 11:05', 'Asia/Tehran'));

    expect(startsLocal(slots($this)))->toBe(['11:15', '11:30']);
});

it('offers nothing on clinic holidays or a vet day off', function (bool $vetOnly) {
    Holiday::create(['date' => '2026-11-08', 'name_fa' => 'تعطیل', 'user_id' => $vetOnly ? $this->vet->id : null]);

    expect(slots($this))->toBe([]);
})->with([true, false]);

it('ignores suspended vets', function () {
    $this->vet->update(['status' => User::STATUS_SUSPENDED]);

    expect(slots($this))->toBe([]);
});

it('rejects dates beyond the horizon', function () {
    $this->withToken($this->ownerToken)
        ->getJson("/api/v1/slots?service_id={$this->service->id}&date=2027-03-01")
        ->assertUnprocessable();
});

it('holds a slot and removes overlapping starts for others', function () {
    holdAt($this, '09:15')->assertCreated()->assertJsonStructure(['data' => ['token', 'expires_at']]);

    expect(startsLocal(slots($this)))->toBe(['10:30', '10:45', '11:00', '11:15', '11:30']);
});

it('refuses a second owner on an overlapping slot', function () {
    holdAt($this, '09:15')->assertCreated();

    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;
    holdAt($this, '09:30', $other)->assertStatus(409);
    holdAt($this, '09:00', $other)->assertStatus(409);
});

it('refuses times that are not offered', function (string $time) {
    holdAt($this, $time)->assertStatus(409);
})->with(['10:00', '09:07', '11:45']);

it('releases a hold after ten minutes', function () {
    holdAt($this, '09:00')->assertCreated();
    expect(startsLocal(slots($this)))->not->toContain('09:00');

    $this->travel(9)->minutes();
    expect(startsLocal(slots($this)))->not->toContain('09:00');

    $this->travel(2)->minutes();
    expect(startsLocal(slots($this)))->toContain('09:00');
});

it('keeps one hold per owner', function () {
    holdAt($this, '09:00')->assertCreated();
    holdAt($this, '11:00')->assertCreated();

    expect(startsLocal(slots($this)))->toContain('09:00')->not->toContain('11:00');
});

it('lets only the holder release a hold', function () {
    $token = holdAt($this, '09:00')->json('data.token');

    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->withToken($other)->deleteJson("/api/v1/slot-holds/{$token}")->assertNotFound();
    expect(startsLocal(slots($this)))->not->toContain('09:00');

    $this->withToken($this->ownerToken)->deleteJson("/api/v1/slot-holds/{$token}")->assertNoContent();
    expect(startsLocal(slots($this)))->toContain('09:00');
});

it('keeps the schedule admin-only', function () {
    $vetToken = $this->vet->createToken('staff-spa', ['staff'])->plainTextToken;

    $this->withToken($vetToken)->putJson("/api/v1/admin/vets/{$this->vet->id}/schedule", ['blocks' => []])->assertForbidden();
    $this->withToken($vetToken)->postJson('/api/v1/admin/holidays', ['date' => '2026-12-01', 'name_fa' => 'x'])->assertForbidden();
});

it('rejects overlapping work blocks and reversed times', function () {
    $url = "/api/v1/admin/vets/{$this->vet->id}/schedule";

    $this->withToken($this->adminToken)->putJson($url, ['blocks' => [
        ['weekday' => 1, 'kind' => 'work', 'starts_at' => '09:00', 'ends_at' => '12:00'],
        ['weekday' => 1, 'kind' => 'work', 'starts_at' => '11:00', 'ends_at' => '13:00'],
    ]])->assertUnprocessable();

    $this->withToken($this->adminToken)->putJson($url, ['blocks' => [
        ['weekday' => 1, 'kind' => 'work', 'starts_at' => '12:00', 'ends_at' => '09:00'],
    ]])->assertUnprocessable();
});

it('replaces the week and manages holidays', function () {
    $url = "/api/v1/admin/vets/{$this->vet->id}/schedule";

    $this->withToken($this->adminToken)->putJson($url, ['blocks' => [
        ['weekday' => 2, 'kind' => 'work', 'starts_at' => '16:00', 'ends_at' => '20:00'],
    ]])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.starts_at', '16:00');

    $id = $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/holidays', ['date' => '2026-12-01', 'name_fa' => 'تعطیل'])
        ->assertCreated()->json('data.id');
    $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/holidays', ['date' => '2026-12-01', 'name_fa' => 'تعطیل'])
        ->assertUnprocessable();

    $this->withToken($this->adminToken)->deleteJson("/api/v1/admin/holidays/{$id}")->assertNoContent();
});

it('returns 404 for the schedule of a non-vet', function () {
    $this->withToken($this->adminToken)->getJson("/api/v1/admin/vets/{$this->owner->id}/schedule")->assertNotFound();
});
