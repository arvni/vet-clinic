<?php

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Role;
use App\Modules\Invoices\InvoiceService;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\PrescriptionFee;
use App\Modules\Payments\Models\Payment;
use App\Modules\Pets\Models\Pet;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->ownerToken = $this->owner->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->vetToken = User::factory()->staff(Role::Vet)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    $this->adminToken = User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    $service = Service::create(['code' => 'consult', 'name_fa' => 'مشاوره', 'category' => 'consult', 'duration_minutes' => 20, 'locations' => ['online']]);
    $this->booking = Booking::create([
        'owner_id' => $this->owner->id,
        'pet_id' => Pet::factory()->create(['owner_id' => $this->owner->id])->id,
        'service_id' => $service->id,
        'vet_id' => User::factory()->staff(Role::Vet)->create()->id,
        'location' => 'online',
        'status' => Booking::CONFIRMED,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addMinutes(20),
        'service_price_rials' => 8_000_000,
    ]);
    $this->url = "/api/v1/staff/bookings/{$this->booking->id}/prescription-fee";
});

function setFee($test): void
{
    $test->withToken($test->adminToken)->putJson('/api/v1/admin/prescription-fee', ['toman' => 200_000])
        ->assertOk()->assertJsonPath('data.rials', '2000000');
}

it('adds the fee to the consult invoice while it is a draft', function () {
    setFee($this);

    $this->withToken($this->vetToken)->postJson($this->url)
        ->assertCreated()
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.supplement_of_id', null)
        ->assertJsonPath('data.total.rials', '10000000');
});

it('bills a linked supplement once the consult invoice is frozen', function () {
    setFee($this);
    $invoices = app(InvoiceService::class);
    $paid = $invoices->issue($invoices->forBooking($this->booking));

    $supplement = $this->withToken($this->vetToken)->postJson($this->url)
        ->assertCreated()
        ->assertJsonPath('data.supplement_of_id', $paid->id)
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.total.rials', '2000000');

    $this->withToken($this->ownerToken)->postJson("/api/v1/invoices/{$supplement->json('data.id')}/pay")->assertOk();
    expect(Payment::query()->firstOrFail()->amount_rials)->toBe(2_000_000);
});

it('charges once per booking, on either invoice', function () {
    setFee($this);
    $invoices = app(InvoiceService::class);

    $this->withToken($this->vetToken)->postJson($this->url)->assertCreated();
    $this->withToken($this->vetToken)->postJson($this->url)->assertStatus(409);

    $invoices->issue($invoices->forBooking($this->booking));
    $this->withToken($this->vetToken)->postJson($this->url)->assertStatus(409);
    expect(Invoice::query()->count())->toBe(1);
});

it('refuses without a fee, or outside an active consult', function () {
    $this->withToken($this->vetToken)->postJson($this->url)->assertStatus(409);

    setFee($this);
    $this->booking->update(['status' => Booking::CANCELLED]);
    $this->withToken($this->vetToken)->postJson($this->url)->assertStatus(409);

    $this->booking->update(['status' => Booking::CONFIRMED]);
    $this->booking->service->update(['category' => 'visit']);
    $this->withToken($this->vetToken)->postJson($this->url)->assertStatus(409);
});

it('keeps prescribing and pricing to the right roles', function () {
    $dispatcher = User::factory()->staff(Role::Dispatcher)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    setFee($this);

    $this->withToken($dispatcher)->postJson($this->url)->assertForbidden();
    $this->withToken($this->vetToken)->putJson('/api/v1/admin/prescription-fee', ['toman' => 1])->assertForbidden();
});

it('stops an owner paying someone else\'s invoice or a credit note', function () {
    app(PrescriptionFee::class)->save(2_000_000);
    $invoices = app(InvoiceService::class);
    $invoice = $invoices->issue($invoices->forBooking($this->booking));
    $note = $invoices->creditNote($invoice);
    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;

    $this->withToken($other)->postJson("/api/v1/invoices/{$invoice->id}/pay")->assertNotFound();
    $this->withToken($this->ownerToken)->postJson("/api/v1/invoices/{$note->id}/pay")->assertStatus(409);
});
