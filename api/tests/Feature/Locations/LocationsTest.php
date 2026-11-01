<?php

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Locations\Maps\MapProvider;
use App\Modules\Locations\Maps\MapUnavailableException;
use App\Modules\Locations\Maps\NeshanMapProvider;
use App\Modules\Locations\Models\Address;
use App\Modules\Locations\Models\ServiceZone;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->adminToken = User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    $this->owner = User::factory()->create();
    $this->ownerToken = $this->owner->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->zone = ServiceZone::create([
        'name_fa' => 'بندرعباس', 'name_en' => 'Bandar Abbas',
        'polygon' => [[27.10, 56.10], [27.10, 56.40], [27.30, 56.40], [27.30, 56.10]],
        'home_visit_fee_rials' => 5_000_000,
    ]);
});

function addressPayload(array $overrides = []): array
{
    return array_merge(['label' => 'خانه', 'line' => 'بندرعباس، بلوار', 'lat' => 27.18, 'lng' => 56.26], $overrides);
}

it('finds the zone for a point inside it', function () {
    $this->withToken($this->ownerToken)->getJson('/api/v1/zones/lookup?lat=27.18&lng=56.26')
        ->assertOk()
        ->assertJsonPath('data.home_available', true)
        ->assertJsonPath('data.zone.name', 'بندرعباس')
        ->assertJsonPath('data.zone.home_visit_fee.rials', '5000000');
});

it('disables home visits outside every zone with an alternative', function () {
    $this->withToken($this->ownerToken)->getJson('/api/v1/zones/lookup?lat=26.5&lng=56.26')
        ->assertOk()
        ->assertJsonPath('data.home_available', false)
        ->assertJsonPath('data.zone', null)
        ->assertJsonPath('data.message', __('locations.outside_zones'));
});

it('handles a concave polygon', function () {
    // U shape: the notch between the arms is outside.
    $u = ServiceZone::create(['name_fa' => 'U', 'home_visit_fee_rials' => 0, 'polygon' => [
        [30, 50], [30, 53], [33, 53], [33, 52], [31, 52], [31, 51], [33, 51], [33, 50],
    ]]);

    expect($u->contains(32, 50.5))->toBeTrue()
        ->and($u->contains(32, 52.5))->toBeTrue()
        ->and($u->contains(32, 51.5))->toBeFalse();
});

it('ignores inactive zones and picks the first by sort', function () {
    ServiceZone::create(['name_fa' => 'مرکز', 'sort' => 1, 'home_visit_fee_rials' => 1, 'polygon' => [[27.15, 56.2], [27.15, 56.3], [27.2, 56.3], [27.2, 56.2]]]);
    expect(ServiceZone::findFor(27.18, 56.26)->name_fa)->toBe('مرکز');

    ServiceZone::query()->update(['active' => false]);
    expect(ServiceZone::findFor(27.18, 56.26))->toBeNull();
});

it('saves an address and reports its zone', function () {
    $this->withToken($this->ownerToken)->postJson('/api/v1/addresses', addressPayload())
        ->assertCreated()->assertJsonPath('data.zone.id', $this->zone->id);

    $this->withToken($this->ownerToken)->postJson('/api/v1/addresses', addressPayload(['lat' => 26.5]))
        ->assertCreated()->assertJsonPath('data.zone', null);

    $this->withToken($this->ownerToken)->getJson('/api/v1/addresses')->assertOk()->assertJsonCount(2, 'data');
});

it('keeps addresses private to their owner', function () {
    $id = $this->withToken($this->ownerToken)->postJson('/api/v1/addresses', addressPayload())->json('data.id');
    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;

    $this->withToken($other)->getJson('/api/v1/addresses')->assertOk()->assertJsonCount(0, 'data');
    $this->withToken($other)->patchJson("/api/v1/addresses/{$id}", ['label' => 'x'])->assertNotFound();
    $this->withToken($other)->deleteJson("/api/v1/addresses/{$id}")->assertNotFound();

    $this->withToken($this->ownerToken)->patchJson("/api/v1/addresses/{$id}", ['label' => 'کار'])->assertOk()->assertJsonPath('data.label', 'کار');
    $this->withToken($this->ownerToken)->deleteJson("/api/v1/addresses/{$id}")->assertNoContent();
    expect(Address::query()->count())->toBe(0);
});

it('rejects coordinates outside Iran', function () {
    $this->withToken($this->ownerToken)->postJson('/api/v1/addresses', addressPayload(['lat' => 51.5, 'lng' => -0.1]))->assertUnprocessable();
});

it('lets admins manage zones with Persian-digit fees', function () {
    $id = $this->withToken($this->adminToken)->postJson('/api/v1/admin/zones', [
        'name_fa' => 'میناب', 'home_visit_fee_toman' => '۱٬۰۰۰٬۰۰۰',
        'polygon' => [[27.0, 57.0], [27.0, 57.2], [27.2, 57.2]],
    ])->assertCreated()->assertJsonPath('data.home_visit_fee.rials', '10000000')->json('data.id');

    $this->withToken($this->adminToken)->patchJson("/api/v1/admin/zones/{$id}", ['active' => false])
        ->assertOk()->assertJsonPath('data.active', false);
    $this->withToken($this->adminToken)->deleteJson("/api/v1/admin/zones/{$id}")->assertNoContent();
});

it('rejects a polygon with too few points', function () {
    $this->withToken($this->adminToken)->postJson('/api/v1/admin/zones', [
        'name_fa' => 'x', 'home_visit_fee_toman' => 1, 'polygon' => [[27, 57], [27, 58]],
    ])->assertUnprocessable();
});

it('keeps zone editing admin-only', function () {
    $vet = User::factory()->staff(Role::Vet)->create()->createToken('staff-spa', ['staff'])->plainTextToken;

    $this->withToken($vet)->getJson('/api/v1/admin/zones')->assertForbidden();
    $this->withToken($vet)->deleteJson("/api/v1/admin/zones/{$this->zone->id}")->assertForbidden();
});

it('proxies map search and reports provider failure', function () {
    $this->withToken($this->ownerToken)->getJson('/api/v1/maps/search?q='.urlencode('کلینیک'))
        ->assertOk()->assertJsonPath('data.0.title', 'کلینیک');

    $this->instance(MapProvider::class, new class implements MapProvider
    {
        public function reverse(float $lat, float $lng): ?string
        {
            throw new MapUnavailableException('down');
        }

        public function search(string $term, float $nearLat, float $nearLng): array
        {
            return [];
        }
    });

    $this->withToken($this->ownerToken)->getJson('/api/v1/maps/reverse?lat=27.18&lng=56.26')->assertStatus(503);
});

it('maps Neshan responses and sends the api key', function () {
    Http::fake([
        '*/v5/reverse*' => Http::response(['formatted_address' => 'بندرعباس، میدان']),
        '*/v1/search*' => Http::response(['items' => [['title' => 'کلینیک', 'address' => 'بندرعباس', 'location' => ['x' => 56.26, 'y' => 27.18]]]]),
    ]);
    $maps = new NeshanMapProvider('key-1');

    expect($maps->reverse(27.18, 56.26))->toBe('بندرعباس، میدان')
        ->and($maps->search('کلینیک', 27, 56))->toBe([['title' => 'کلینیک', 'address' => 'بندرعباس', 'lat' => 27.18, 'lng' => 56.26]]);

    Http::assertSent(fn ($request) => $request->hasHeader('Api-Key', 'key-1'));
});

it('raises when Neshan fails', function () {
    Http::fake(['*' => Http::response([], 500)]);

    (new NeshanMapProvider('key-1'))->reverse(27.18, 56.26);
})->throws(MapUnavailableException::class);
