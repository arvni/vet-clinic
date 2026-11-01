<?php

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Role;
use App\Modules\Locations\Models\Address;
use App\Modules\Locations\Models\ServiceZone;
use App\Modules\Pets\Models\Pet;
use App\Modules\Scheduling\Models\ScheduleBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-08 08:00', 'Asia/Tehran'));

    $this->vet = User::factory()->staff(Role::Vet)->create();
    ScheduleBlock::create(['user_id' => $this->vet->id, 'weekday' => 0, 'kind' => 'work', 'starts_at' => '09:00', 'ends_at' => '12:00']);

    $this->owner = User::factory()->create();
    $this->ownerToken = $this->owner->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->pet = Pet::factory()->create(['owner_id' => $this->owner->id, 'species' => 'dog']);
    $this->service = Service::create([
        'code' => 'visit30', 'name_fa' => 'ویزیت', 'category' => 'visit',
        'duration_minutes' => 30, 'locations' => ['clinic', 'home'],
    ]);
    $this->service->prices()->create(['price_rials' => 10_000_000, 'effective_from' => now()->subDay()]);
    $this->zone = ServiceZone::create([
        'name_fa' => 'بندرعباس', 'home_visit_fee_rials' => 5_000_000,
        'polygon' => [[27.10, 56.10], [27.10, 56.40], [27.30, 56.40], [27.30, 56.10]],
    ]);
    $this->address = Address::create(['user_id' => $this->owner->id, 'label' => 'خانه', 'line' => 'بلوار', 'lat' => 27.18, 'lng' => 56.26]);
});

function hold($test, string $time = '09:00', ?string $token = null): string
{
    return $test->withToken($token ?? $test->ownerToken)->postJson('/api/v1/slot-holds', [
        'service_id' => $test->service->id,
        'vet_id' => $test->vet->id,
        'starts_at' => Carbon::parse('2026-11-08 '.$time, 'Asia/Tehran')->toIso8601String(),
    ])->assertCreated()->json('data.token');
}

function book($test, array $overrides = [])
{
    return $test->withToken($test->ownerToken)->postJson('/api/v1/bookings', array_merge([
        'hold_token' => hold($test), 'pet_id' => $test->pet->id, 'location' => 'clinic',
    ], $overrides));
}

it('confirms a clinic booking with a price snapshot', function () {
    book($this)->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.service_price.rials', '10000000')
        ->assertJsonPath('data.home_visit_fee.rials', '0');

    $this->service->prices()->create(['price_rials' => 20_000_000, 'effective_from' => now()]);

    $this->withToken($this->ownerToken)->getJson('/api/v1/bookings')
        ->assertOk()->assertJsonPath('data.0.service_price.rials', '10000000');
});

it('leaves a home visit unpaid until the zone fee is paid', function () {
    book($this, ['location' => 'home', 'address_id' => $this->address->id])->assertCreated()
        ->assertJsonPath('data.status', 'pending_payment')
        ->assertJsonPath('data.home_visit_fee.rials', '5000000');
});

it('refuses a home visit outside every zone', function () {
    $far = Address::create(['user_id' => $this->owner->id, 'label' => 'x', 'line' => 'y', 'lat' => 26.5, 'lng' => 56.26]);

    book($this, ['location' => 'home', 'address_id' => $far->id])->assertUnprocessable()->assertJsonValidationErrors('address_id');
});

it('refuses another owner\'s address and pet', function () {
    $other = User::factory()->create();
    $address = Address::create(['user_id' => $other->id, 'label' => 'x', 'line' => 'y', 'lat' => 27.18, 'lng' => 56.26]);
    $pet = Pet::factory()->create(['owner_id' => $other->id]);

    book($this, ['location' => 'home', 'address_id' => $address->id])->assertNotFound();
    book($this, ['pet_id' => $pet->id])->assertNotFound();
});

it('refuses a location the service does not offer', function () {
    book($this, ['location' => 'online'])->assertUnprocessable()->assertJsonValidationErrors('location');
});

it('refuses a species the service does not treat', function () {
    $this->service->update(['species' => ['cat']]);

    book($this)->assertUnprocessable()->assertJsonValidationErrors('pet_id');
});

it('refuses an expired or foreign hold', function () {
    $token = hold($this);
    $this->travel(11)->minutes();
    book($this, ['hold_token' => $token])->assertStatus(409);

    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;
    book($this, ['hold_token' => hold($this, '10:00', $other)])->assertStatus(409);
});

it('takes the slot off the market once booked', function () {
    book($this)->assertCreated();

    $starts = $this->withToken($this->ownerToken)
        ->getJson("/api/v1/slots?service_id={$this->service->id}&date=2026-11-08")->json('data.*.starts_at');

    expect($starts)->not->toContain(Carbon::parse('2026-11-08 09:00', 'Asia/Tehran')->toIso8601ZuluString())
        ->not->toContain(Carbon::parse('2026-11-08 09:15', 'Asia/Tehran')->toIso8601ZuluString())
        ->toContain(Carbon::parse('2026-11-08 09:30', 'Asia/Tehran')->toIso8601ZuluString());
});

it('cannot double book even if the cache forgets a hold', function () {
    $first = hold($this);
    Cache::flush();
    $second = hold($this);

    Booking::create([
        'owner_id' => $this->owner->id, 'pet_id' => $this->pet->id, 'service_id' => $this->service->id,
        'vet_id' => $this->vet->id, 'location' => 'clinic', 'status' => Booking::CONFIRMED,
        'starts_at' => Carbon::parse('2026-11-08 09:00', 'Asia/Tehran'),
        'ends_at' => Carbon::parse('2026-11-08 09:30', 'Asia/Tehran'),
    ]);

    expect($first)->not->toBe($second);
    book($this, ['hold_token' => $second])->assertStatus(409);
});

it('lets an owner move a hold to an overlapping time', function () {
    hold($this, '09:00');
    hold($this, '09:15');
    hold($this, '09:15');
});

it('hides bookings from other owners', function () {
    $id = book($this)->json('data.id');
    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;

    $this->withToken($other)->getJson("/api/v1/bookings/{$id}")->assertNotFound();
    $this->withToken($other)->getJson('/api/v1/bookings')->assertJsonCount(0, 'data');
});

it('cancels inside the window and frees the slot', function () {
    $this->travelTo(Carbon::parse('2026-11-07 08:00', 'Asia/Tehran'));
    $id = $this->withToken($this->ownerToken)->postJson('/api/v1/bookings', [
        'hold_token' => $this->withToken($this->ownerToken)->postJson('/api/v1/slot-holds', [
            'service_id' => $this->service->id, 'vet_id' => $this->vet->id,
            'starts_at' => Carbon::parse('2026-11-08 09:00', 'Asia/Tehran')->toIso8601String(),
        ])->json('data.token'),
        'pet_id' => $this->pet->id, 'location' => 'clinic',
    ])->assertCreated()->assertJsonPath('data.can_cancel', true)->json('data.id');

    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$id}/cancel", ['reason' => 'x'])
        ->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_cancel', false);

    expect(hold($this))->toBeString();
});

it('stops owners cancelling after the window', function () {
    $id = book($this)->assertCreated()->assertJsonPath('data.can_cancel', false)->json('data.id');

    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$id}/cancel")->assertStatus(409);
});

it('honours admin-set windows', function () {
    $admin = User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    $this->withToken($admin)->putJson('/api/v1/admin/booking-policy', ['cancel_hours' => 0, 'reschedule_hours' => 0])
        ->assertOk()->assertJsonPath('data.cancel_hours', 0);

    $id = book($this)->assertJsonPath('data.can_cancel', true)->json('data.id');
    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$id}/cancel")->assertOk();
    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$id}/cancel")->assertStatus(409);
});

it('keeps the policy admin-only', function () {
    $vet = $this->vet->createToken('staff-spa', ['staff'])->plainTextToken;

    $this->withToken($vet)->putJson('/api/v1/admin/booking-policy', ['cancel_hours' => 1, 'reschedule_hours' => 1])->assertForbidden();
});

it('reschedules within the window and keeps the price', function () {
    $this->withToken(User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken)
        ->putJson('/api/v1/admin/booking-policy', ['cancel_hours' => 0, 'reschedule_hours' => 0]);
    $id = book($this)->json('data.id');
    $this->service->prices()->create(['price_rials' => 20_000_000, 'effective_from' => now()]);

    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$id}/reschedule", ['hold_token' => hold($this, '10:00')])
        ->assertOk()
        ->assertJsonPath('data.starts_at', Carbon::parse('2026-11-08 10:00', 'Asia/Tehran')->toIso8601ZuluString())
        ->assertJsonPath('data.service_price.rials', '10000000');

    // The old time is free again.
    hold($this, '09:00');
});

it('refuses a reschedule with a hold for another service or after dispatch', function () {
    $this->withToken(User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken)
        ->putJson('/api/v1/admin/booking-policy', ['cancel_hours' => 0, 'reschedule_hours' => 0]);
    $id = book($this)->json('data.id');

    $other = Service::create(['code' => 'other', 'name_fa' => 'x', 'category' => 'visit', 'duration_minutes' => 30, 'locations' => ['clinic']]);
    $token = $this->withToken($this->ownerToken)->postJson('/api/v1/slot-holds', [
        'service_id' => $other->id, 'vet_id' => $this->vet->id,
        'starts_at' => Carbon::parse('2026-11-08 10:00', 'Asia/Tehran')->toIso8601String(),
    ])->json('data.token');
    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$id}/reschedule", ['hold_token' => $token])->assertStatus(409);

    Booking::findOrFail($id)->update(['dispatched_at' => now()]);
    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$id}/reschedule", ['hold_token' => hold($this, '10:30')])->assertStatus(409);
});

it('keeps cancel and reschedule private to the owner', function () {
    $id = book($this)->json('data.id');
    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;

    $this->withToken($other)->postJson("/api/v1/bookings/{$id}/cancel")->assertNotFound();
});
