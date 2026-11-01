<?php

use App\Models\User;
use App\Modules\Emergencies\Models\EmergencyTicket;
use App\Modules\Identity\Role;
use App\Modules\Identity\Sms\FakeSmsSender;
use App\Modules\Identity\Sms\SmsDeliveryException;
use App\Modules\Identity\Sms\SmsSender;
use App\Modules\Pets\Models\Pet;

beforeEach(function () {
    config(['clinic.phone' => '07633334444']);
    $this->sms = new FakeSmsSender;
    $this->instance(SmsSender::class, $this->sms);

    $this->owner = User::factory()->create();
    $this->ownerToken = $this->owner->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->pet = Pet::factory()->create(['owner_id' => $this->owner->id]);
    $this->dispatcher = User::factory()->staff(Role::Dispatcher)->create();
    $this->staffToken = $this->dispatcher->createToken('staff-spa', ['staff'])->plainTextToken;
});

function raise($test, array $payload = [])
{
    return $test->withToken($test->ownerToken)->postJson('/api/v1/emergencies', $payload);
}

it('creates a ticket, returns the phone and alerts dispatchers', function () {
    raise($this, ['pet_id' => $this->pet->id, 'lat' => 27.18, 'lng' => 56.26])
        ->assertCreated()
        ->assertJsonPath('data.phone', '07633334444')
        ->assertJsonPath('data.status', 'open');

    $this->sms->assertSentCount(1);
    expect($this->sms->lastTo($this->dispatcher->mobile))->toHaveKey('ticket');
});

it('works without a pet or location', function () {
    raise($this)->assertCreated();
});

it('reuses the open ticket on a repeat tap without alerting again', function () {
    $id = raise($this)->json('data.id');

    raise($this)->assertOk()->assertJsonPath('data.id', $id);
    $this->sms->assertSentCount(1);
    expect(EmergencyTicket::query()->count())->toBe(1);
});

it('opens a new ticket once the last is resolved', function () {
    $id = raise($this)->json('data.id');
    $this->withToken($this->staffToken)->postJson("/api/v1/staff/emergencies/{$id}/resolve", ['resolution' => 'رزرو شد'])->assertOk();

    raise($this)->assertCreated();
});

it('refuses another owner\'s pet', function () {
    $pet = Pet::factory()->create(['owner_id' => User::factory()->create()->id]);

    raise($this, ['pet_id' => $pet->id])->assertNotFound();
});

it('keeps the ticket when an alert fails', function () {
    $this->instance(SmsSender::class, new class implements SmsSender
    {
        public function sendTemplate(string $mobile, string $template, array $tokens): void
        {
            throw new SmsDeliveryException('down');
        }
    });

    raise($this)->assertCreated();
    expect(EmergencyTicket::query()->count())->toBe(1);
});

it('shows the queue with the SLA state and clears it on acknowledge', function () {
    $id = raise($this, ['pet_id' => $this->pet->id])->json('data.id');

    $this->travel(16)->minutes();
    $this->withToken($this->staffToken)->getJson('/api/v1/staff/emergencies')
        ->assertOk()
        ->assertJsonPath('data.0.pet.name', $this->pet->name)
        ->assertJsonPath('data.0.owner.mobile', $this->owner->mobile)
        ->assertJsonPath('data.0.sla_breached', true);

    $this->withToken($this->staffToken)->postJson("/api/v1/staff/emergencies/{$id}/acknowledge")
        ->assertOk()->assertJsonPath('data.status', 'acknowledged')->assertJsonPath('data.sla_breached', false);
});

it('drops resolved tickets from the queue', function () {
    $id = raise($this)->json('data.id');
    $this->withToken($this->staffToken)->postJson("/api/v1/staff/emergencies/{$id}/resolve", ['resolution' => 'x'])->assertOk();

    $this->withToken($this->staffToken)->getJson('/api/v1/staff/emergencies')->assertJsonCount(0, 'data');
});

it('keeps the queue away from owners and unrelated staff', function () {
    $vaccinator = User::factory()->staff(Role::Vaccinator)->create()->createToken('staff-spa', ['staff'])->plainTextToken;

    $this->withToken($this->ownerToken)->getJson('/api/v1/staff/emergencies')->assertForbidden();
    $this->withToken($vaccinator)->getJson('/api/v1/staff/emergencies')->assertForbidden();
});
