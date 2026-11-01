<?php

use App\Models\User;
use App\Modules\Audit\AuditLog;
use App\Modules\Pets\Models\Pet;

beforeEach(function () {
    $this->from = User::factory()->owner()->create(['mobile' => '+989121111111']);
    $this->to = User::factory()->owner()->create(['mobile' => '+989122222222']);
    $this->fromToken = $this->from->createToken('spa', ['owner'])->plainTextToken;
    $this->toToken = $this->to->createToken('spa', ['owner'])->plainTextToken;
    $this->pet = Pet::factory()->create(['owner_id' => $this->from->id]);
    $this->pet->weights()->create(['weight_grams' => 5000, 'measured_at' => now(), 'source' => 'vet']);
});

it('transfers a pet after the recipient accepts, keeping its history', function () {
    $transferId = $this->withToken($this->fromToken)
        ->postJson("/api/v1/pets/{$this->pet->id}/transfers", ['to_mobile' => '0912 222 2222'])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($this->toToken)->getJson('/api/v1/pet-transfers/incoming')
        ->assertOk()->assertJsonPath('data.0.pet.id', $this->pet->id);

    $this->withToken($this->toToken)->postJson("/api/v1/pet-transfers/{$transferId}/accept")->assertOk();

    $pet = $this->pet->fresh();
    expect($pet->owner_id)->toBe($this->to->id)
        ->and($pet->weights()->count())->toBe(1)
        ->and(AuditLog::where('action', 'pet.transferred')->exists())->toBeTrue();
});

it('lets only the recipient accept', function () {
    $transferId = $this->withToken($this->fromToken)
        ->postJson("/api/v1/pets/{$this->pet->id}/transfers", ['to_mobile' => '09122222222'])->json('data.id');

    $this->withToken($this->fromToken)->postJson("/api/v1/pet-transfers/{$transferId}/accept")->assertNotFound();
    expect($this->pet->fresh()->owner_id)->toBe($this->from->id);
});

it('expires after 7 days', function () {
    $transferId = $this->withToken($this->fromToken)
        ->postJson("/api/v1/pets/{$this->pet->id}/transfers", ['to_mobile' => '09122222222'])->json('data.id');

    $this->travel(8)->days();

    $this->withToken($this->toToken)->postJson("/api/v1/pet-transfers/{$transferId}/accept")->assertStatus(422);
});

it('allows one pending transfer per pet and not to yourself', function () {
    $this->withToken($this->fromToken)->postJson("/api/v1/pets/{$this->pet->id}/transfers", ['to_mobile' => '09122222222'])->assertCreated();
    $this->withToken($this->fromToken)->postJson("/api/v1/pets/{$this->pet->id}/transfers", ['to_mobile' => '09123333333'])->assertStatus(422);

    $other = Pet::factory()->create(['owner_id' => $this->from->id]);
    $this->withToken($this->fromToken)->postJson("/api/v1/pets/{$other->id}/transfers", ['to_mobile' => '09121111111'])->assertStatus(422);
});

it('lets the sender cancel', function () {
    $transferId = $this->withToken($this->fromToken)
        ->postJson("/api/v1/pets/{$this->pet->id}/transfers", ['to_mobile' => '09122222222'])->json('data.id');

    $this->withToken($this->fromToken)->postJson("/api/v1/pet-transfers/{$transferId}/cancel")->assertNoContent();
    $this->withToken($this->toToken)->postJson("/api/v1/pet-transfers/{$transferId}/accept")->assertStatus(422);
});

it('forbids transferring someone else\'s pet', function () {
    $this->withToken($this->toToken)->postJson("/api/v1/pets/{$this->pet->id}/transfers", ['to_mobile' => '09123333333'])
        ->assertForbidden();
});
