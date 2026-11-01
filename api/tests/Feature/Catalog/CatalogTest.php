<?php

use App\Models\User;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServicePrice;
use App\Modules\Catalog\Models\Vaccine;
use App\Modules\Identity\Role;
use Database\Seeders\BreedSeeder;
use Database\Seeders\ServiceSeeder;

beforeEach(function () {
    $this->seed([BreedSeeder::class, ServiceSeeder::class]);
    $this->admin = User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
});

function rabiesVaccine(array $overrides = []): array
{
    return array_merge([
        'code' => 'rabies_nobivac',
        'name_fa' => 'هاری نوبیواک',
        'name_en' => 'Nobivac Rabies',
        'manufacturer' => 'MSD',
        'diseases' => [['fa' => 'هاری', 'en' => 'Rabies']],
        'species' => ['dog', 'cat'],
        'min_age_weeks' => 12,
        'primary_doses' => 1,
        'booster_interval_days' => 365,
        'vaccine_cost_toman' => '۴۵۰٬۰۰۰',
        'service_fee_toman' => 100000,
        'injection_fee_toman' => 50000,
    ], $overrides);
}

it('seeds the 7 launch services and lists them in order', function () {
    $this->getJson('/api/v1/catalog/services')
        ->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonPath('data.0.code', 'general_visit')
        ->assertJsonPath('data.0.name', 'ویزیت عمومی')
        ->assertJsonPath('data.0.price', null);
});

it('localizes service names and falls back to Persian', function () {
    Service::where('code', 'emergency')->update(['name_en' => null]);

    $this->getJson('/api/v1/catalog/services', ['Accept-Language' => 'en'])
        ->assertJsonPath('data.0.name', 'General visit')
        ->assertJsonPath('data.6.name', 'اورژانس');
});

it('filters services by location', function () {
    $this->getJson('/api/v1/catalog/services?location=online')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'online_consult');
});

it('lets an admin add an 8th service without a deploy', function () {
    $this->withToken($this->admin)->postJson('/api/v1/admin/services', [
        'code' => 'microchipping',
        'name_fa' => 'نصب ميكروچيپ',
        'name_en' => 'Microchipping',
        'category' => 'other',
        'duration_minutes' => 15,
        'locations' => ['clinic'],
        'species' => ['dog', 'cat'],
        'price_toman' => '۳۰۰٬۰۰۰',
    ])->assertCreated()
        ->assertJsonPath('data.name_fa', 'نصب میکروچیپ')
        ->assertJsonPath('data.price.rials', '3000000')
        ->assertJsonPath('data.price.formatted', '۳۰۰٬۰۰۰ تومان');

    $this->getJson('/api/v1/catalog/services')->assertJsonCount(8, 'data');
    $this->getJson('/api/v1/catalog/services?species=bird')->assertJsonCount(7, 'data');
});

it('hides disabled services from the public catalog only', function () {
    $service = Service::where('code', 'emergency')->sole();

    $this->withToken($this->admin)->patchJson("/api/v1/admin/services/{$service->id}", ['active' => false])->assertOk();

    $this->getJson('/api/v1/catalog/services')->assertJsonCount(6, 'data');
    $this->withToken($this->admin)->getJson('/api/v1/admin/services')->assertJsonCount(7, 'data');
});

it('applies future prices only from their effective date', function () {
    $service = Service::where('code', 'general_visit')->sole();
    $this->travelTo('2026-11-01 08:00:00');

    $this->withToken($this->admin)->postJson("/api/v1/admin/services/{$service->id}/prices", ['price_toman' => 500000])->assertOk();
    $this->withToken($this->admin)->postJson("/api/v1/admin/services/{$service->id}/prices", [
        'price_toman' => 600000,
        'effective_from' => '2026-11-20T00:00:00Z',
    ])->assertOk();

    $this->getJson('/api/v1/catalog/services')->assertJsonPath('data.0.price.rials', '5000000');

    $this->travelTo('2026-11-21 08:00:00');
    $this->getJson('/api/v1/catalog/services')->assertJsonPath('data.0.price.rials', '6000000');

    expect(fn () => ServicePrice::first()->update(['price_rials' => 1]))->toThrow(LogicException::class);
});

it('rejects back-dated prices', function () {
    $service = Service::where('code', 'general_visit')->sole();

    $this->withToken($this->admin)->postJson("/api/v1/admin/services/{$service->id}/prices", [
        'price_toman' => 500000, 'effective_from' => now()->subDay()->toIso8601String(),
    ])->assertStatus(422);
});

it('forbids catalog changes for non-admins', function () {
    $vet = User::factory()->staff(Role::Vet)->create()->createToken('staff-spa', ['staff'])->plainTextToken;

    $this->withToken($vet)->postJson('/api/v1/admin/services', [])->assertForbidden();
    $this->withToken($vet)->postJson('/api/v1/admin/vaccines', rabiesVaccine())->assertForbidden();
});

it('stores vaccine prices in rials and shows the breakdown', function () {
    $this->withToken($this->admin)->postJson('/api/v1/admin/vaccines', rabiesVaccine())
        ->assertCreated()
        ->assertJsonPath('data.price.vaccine.rials', '4500000')
        ->assertJsonPath('data.price.total.rials', '6000000')
        ->assertJsonPath('data.price.total.formatted', '۶۰۰٬۰۰۰ تومان')
        ->assertJsonPath('data.diseases', ['هاری']);
});

it('requires a dose interval for multi-dose series', function () {
    $this->withToken($this->admin)->postJson('/api/v1/admin/vaccines', rabiesVaccine(['primary_doses' => 2]))
        ->assertStatus(422)->assertJsonValidationErrors('primary_interval_days');
});

it('lists vaccines for a species and age (FR-4.2)', function () {
    $this->withToken($this->admin)->postJson('/api/v1/admin/vaccines', rabiesVaccine())->assertCreated();
    $this->withToken($this->admin)->postJson('/api/v1/admin/vaccines', rabiesVaccine([
        'code' => 'dhppi', 'name_fa' => 'چندگانه سگ', 'species' => ['dog'], 'min_age_weeks' => 6,
        'primary_doses' => 3, 'primary_interval_days' => 21,
    ]))->assertCreated();

    $this->getJson('/api/v1/catalog/vaccines?species=dog&age_weeks=8')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'dhppi');
    $this->getJson('/api/v1/catalog/vaccines?species=dog&age_weeks=16')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/catalog/vaccines?species=cat')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/catalog/vaccines?species=bird')->assertJsonCount(0, 'data');

    expect(Vaccine::count())->toBe(2);
});

it('lists breeds per species in the request language', function () {
    $this->getJson('/api/v1/catalog/breeds?species=cat', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'Persian']);

    $this->getJson('/api/v1/catalog/species')->assertJsonPath('data.0', ['value' => 'dog', 'label' => 'سگ']);
});
