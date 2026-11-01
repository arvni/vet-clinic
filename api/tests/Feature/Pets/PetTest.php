<?php

use App\Models\User;
use App\Modules\Identity\Role;
use App\Modules\Pets\Models\Breed;
use App\Modules\Pets\Models\Pet;
use Database\Seeders\BreedSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(BreedSeeder::class);
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('spa', ['owner'])->plainTextToken;
});

it('creates a pet with a first weight and Jalali birth date', function () {
    $breed = Breed::where('name_en', 'Persian')->sole();

    $this->travelTo('2026-11-01 08:00:00');

    $this->withToken($this->token)->postJson('/api/v1/pets', [
        'name' => 'ملوس',
        'species' => 'cat',
        'breed_id' => $breed->id,
        'sex' => 'female',
        'birth_date_jalali' => '۱۴۰۳/۰۸/۱۰',
        'weight_kg' => '۴.۲',
        'neutered' => true,
    ])->assertCreated()
        ->assertJsonPath('data.species.label', 'گربه')
        ->assertJsonPath('data.breed.name', 'پرشین')
        ->assertJsonPath('data.birth_date', '2024-10-31')
        ->assertJsonPath('data.birth_date_jalali', '۱۴۰۳/۰۸/۱۰')
        ->assertJsonPath('data.age', ['years' => 2, 'months' => 0, 'estimated' => false])
        ->assertJsonPath('data.latest_weight.kg', '4.200');
});

it('rejects a breed from another species and a future birth date', function () {
    $dogBreed = Breed::where('species', 'dog')->first();

    $this->withToken($this->token)->postJson('/api/v1/pets', [
        'name' => 'Tom', 'species' => 'cat', 'breed_id' => $dogBreed->id,
    ])->assertStatus(422)->assertJsonValidationErrors('breed_id');

    $this->withToken($this->token)->postJson('/api/v1/pets', [
        'name' => 'Tom', 'species' => 'cat', 'birth_date' => now()->addDay()->toDateString(),
    ])->assertStatus(422)->assertJsonValidationErrors('birth_date');
});

it('lists only the owner\'s own pets', function () {
    Pet::factory()->count(2)->create(['owner_id' => $this->owner->id]);
    Pet::factory()->create(); // someone else's

    $this->withToken($this->token)->getJson('/api/v1/pets')->assertOk()->assertJsonCount(2, 'data');
});

it('hides other owners\' pets', function () {
    $other = Pet::factory()->create();

    $this->withToken($this->token)->getJson("/api/v1/pets/{$other->id}")->assertForbidden();
    $this->withToken($this->token)->patchJson("/api/v1/pets/{$other->id}", ['name' => 'X'])->assertForbidden();
    $this->withToken($this->token)->deleteJson("/api/v1/pets/{$other->id}")->assertForbidden();
});

it('lets staff view any pet but not delete it', function () {
    $pet = Pet::factory()->create();
    $vet = User::factory()->staff(Role::Vet)->create();
    $staffToken = $vet->createToken('staff-spa', ['staff'])->plainTextToken;

    $this->withToken($staffToken)->getJson("/api/v1/pets/{$pet->id}")->assertOk();
    $this->withToken($staffToken)->deleteJson("/api/v1/pets/{$pet->id}")->assertForbidden();
});

it('records a weight history with the source', function () {
    $pet = Pet::factory()->create(['owner_id' => $this->owner->id]);

    $this->withToken($this->token)->postJson("/api/v1/pets/{$pet->id}/weights", ['weight_kg' => '12.5'])
        ->assertCreated()->assertJsonPath('data.source', 'owner');

    $vet = User::factory()->staff(Role::Vet)->create();
    $this->withToken($vet->createToken('staff-spa', ['staff'])->plainTextToken)
        ->postJson("/api/v1/pets/{$pet->id}/weights", ['weight_kg' => 12.8])
        ->assertCreated()->assertJsonPath('data.source', 'vet');

    $this->withToken($this->token)->getJson("/api/v1/pets/{$pet->id}/weights")
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.kg', '12.800');
});

it('manages allergies', function () {
    $pet = Pet::factory()->create(['owner_id' => $this->owner->id]);

    $id = $this->withToken($this->token)->postJson("/api/v1/pets/{$pet->id}/allergies", [
        'type' => 'drug', 'substance' => 'پني‌سیلین',
    ])->assertCreated()->assertJsonPath('data.substance', 'پنی‌سیلین')->json('data.id');

    $this->withToken($this->token)->deleteJson("/api/v1/pets/{$pet->id}/allergies/{$id}")->assertNoContent();
    expect($pet->allergies()->count())->toBe(0);
});

it('uploads a photo and serves it through a signed URL only', function () {
    $pet = Pet::factory()->create(['owner_id' => $this->owner->id]);

    $url = $this->withToken($this->token)
        ->post("/api/v1/pets/{$pet->id}/photo", ['photo' => UploadedFile::fake()->image('cat.jpg')], ['Accept' => 'application/json'])
        ->assertOk()
        ->json('data.photo_url');

    expect($url)->toContain('signature=');
    $this->get($url)->assertOk();

    $unsigned = strtok($url, '?');
    $this->get($unsigned)->assertForbidden();
});

it('soft deletes a pet', function () {
    $pet = Pet::factory()->create(['owner_id' => $this->owner->id]);

    $this->withToken($this->token)->deleteJson("/api/v1/pets/{$pet->id}")->assertNoContent();

    expect(Pet::find($pet->id))->toBeNull()
        ->and(Pet::withTrashed()->find($pet->id))->not->toBeNull();
});
