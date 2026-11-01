<?php

use App\Models\User;
use App\Modules\Audit\AuditLog;
use App\Modules\Identity\Role;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Models\PetDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Storage::fake('local');
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('spa', ['owner'])->plainTextToken;
    $this->pet = Pet::factory()->create(['owner_id' => $this->owner->id]);
    $vet = User::factory()->staff(Role::Vet)->create();
    $this->staffToken = $vet->createToken('staff-spa', ['staff'])->plainTextToken;
});

function uploadNational(string $token, Pet $pet, string $number = '۱۲۳۴۵۶۷۸'): TestResponse
{
    return test()->withToken($token)->post("/api/v1/pets/{$pet->id}/documents", [
        'kind' => 'national',
        'number' => $number,
        'file' => UploadedFile::fake()->create('id.pdf', 200, 'application/pdf'),
    ], ['Accept' => 'application/json']);
}

it('records a national document as unverified', function () {
    uploadNational($this->token, $this->pet)
        ->assertCreated()
        ->assertJsonPath('data.number', '12345678')
        ->assertJsonPath('data.verified', false)
        ->assertJsonPath('data.kind.label', 'شناسنامه ملی');
});

it('requires number and scan for a national document', function () {
    $this->withToken($this->token)->postJson("/api/v1/pets/{$this->pet->id}/documents", ['kind' => 'national'])
        ->assertStatus(422)->assertJsonValidationErrors(['number', 'file']);
});

it('keeps national numbers unique across pets', function () {
    uploadNational($this->token, $this->pet)->assertCreated();
    $otherPet = Pet::factory()->create(['owner_id' => $this->owner->id]);

    uploadNational($this->token, $otherPet)->assertStatus(422)
        ->assertJsonPath('errors.number.0', 'این شماره شناسنامه ملی برای حیوان دیگری ثبت شده است.');
});

it('lets only staff verify, records who and when, and audits it', function () {
    $id = uploadNational($this->token, $this->pet)->json('data.id');

    $this->withToken($this->token)->postJson("/api/v1/staff/pet-documents/{$id}/verify", ['verified' => true])
        ->assertForbidden();

    $this->withToken($this->staffToken)->postJson("/api/v1/staff/pet-documents/{$id}/verify", ['verified' => true])
        ->assertOk()
        ->assertJsonPath('data.verified', true);

    $doc = PetDocument::find($id);
    expect($doc->verified_by)->not->toBeNull()
        ->and($doc->verified_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'pet_document.verified')->exists())->toBeTrue();
});

it('clears verification when the owner edits the number or scan', function () {
    $id = uploadNational($this->token, $this->pet)->json('data.id');
    $this->withToken($this->staffToken)->postJson("/api/v1/staff/pet-documents/{$id}/verify", ['verified' => true]);

    $this->withToken($this->token)->postJson("/api/v1/pets/{$this->pet->id}/documents/{$id}", ['number' => '87654321'])
        ->assertOk()
        ->assertJsonPath('data.verified', false);

    $this->withToken($this->staffToken)->postJson("/api/v1/staff/pet-documents/{$id}/verify", ['verified' => true]);
    $this->withToken($this->token)->post("/api/v1/pets/{$this->pet->id}/documents/{$id}", [
        'file' => UploadedFile::fake()->image('new.jpg'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.verified', false);
});

it('ignores a verified flag sent by the owner', function () {
    $id = uploadNational($this->token, $this->pet)->json('data.id');

    $this->withToken($this->token)->postJson("/api/v1/pets/{$this->pet->id}/documents/{$id}", ['verified' => true, 'number' => '11112222'])
        ->assertOk()
        ->assertJsonPath('data.verified', false);
});

it('rejects disallowed file types', function () {
    $this->withToken($this->token)->post("/api/v1/pets/{$this->pet->id}/documents", [
        'kind' => 'other',
        'file' => UploadedFile::fake()->create('evil.html', 10, 'text/html'),
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('stops other owners from reading documents', function () {
    uploadNational($this->token, $this->pet);
    $stranger = User::factory()->owner()->create()->createToken('spa', ['owner'])->plainTextToken;

    $this->withToken($stranger)->getJson("/api/v1/pets/{$this->pet->id}/documents")->assertForbidden();
});
