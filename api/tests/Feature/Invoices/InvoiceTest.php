<?php

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Role;
use App\Modules\Invoices\InvoiceException;
use App\Modules\Invoices\InvoiceService;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceLine;
use App\Modules\Pets\Models\Pet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-08 08:00', 'Asia/Tehran'));
    $this->owner = User::factory()->create();
    $this->ownerToken = $this->owner->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->staffToken = User::factory()->staff(Role::Dispatcher)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    $this->service = Service::create([
        'code' => 'visit', 'name_fa' => 'ویزیت', 'category' => 'visit', 'duration_minutes' => 30, 'locations' => ['home'],
    ]);
    $this->service->prices()->create(['price_rials' => 10_000_000, 'effective_from' => now()->subDay()]);
    $this->booking = invoiceBooking($this);
    $this->invoices = app(InvoiceService::class);
});

function invoiceBooking($test): Booking
{
    static $offset = 0;
    $offset += 2;

    return Booking::create([
        'owner_id' => $test->owner->id,
        'pet_id' => Pet::factory()->create(['owner_id' => $test->owner->id])->id,
        'service_id' => $test->service->id,
        'vet_id' => User::factory()->staff(Role::Vet)->create()->id,
        'location' => 'home',
        'status' => Booking::CONFIRMED,
        'starts_at' => now()->addHours($offset),
        'ends_at' => now()->addHours($offset + 1),
        'service_price_rials' => 10_000_000,
        'home_visit_fee_rials' => 5_000_000,
    ]);
}

it('drafts an invoice from the booking snapshots, once', function () {
    $response = $this->withToken($this->staffToken)->postJson("/api/v1/staff/bookings/{$this->booking->id}/invoice")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.number', null)
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.total.rials', '15000000');

    $this->withToken($this->staffToken)->postJson("/api/v1/staff/bookings/{$this->booking->id}/invoice")
        ->assertJsonPath('data.id', $response->json('data.id'));
});

it('updates totals as lines come and go on a draft', function () {
    $id = $this->invoices->forBooking($this->booking)->id;

    $line = $this->withToken($this->staffToken)->postJson("/api/v1/staff/invoices/{$id}/lines", [
        'description_fa' => 'دارو', 'unit_price_toman' => '۲۰۰٬۰۰۰', 'quantity' => 2,
    ])->assertCreated()->assertJsonPath('data.total.rials', '19000000')->json('data.lines.2.id');

    $this->withToken($this->staffToken)->deleteJson("/api/v1/staff/invoices/{$id}/lines/{$line}")
        ->assertOk()->assertJsonPath('data.total.rials', '15000000');
});

it('numbers issued invoices without gaps per Jalali year', function () {
    $first = $this->invoices->issue($this->invoices->forBooking($this->booking));
    $second = $this->invoices->issue($this->invoices->forBooking(invoiceBooking($this)));

    expect($first->number)->toBe('INV-1405-000001')
        ->and($second->number)->toBe('INV-1405-000002')
        ->and($first->issued_at)->not->toBeNull();
});

it('refuses to issue an empty invoice and keeps the sequence', function () {
    $empty = Invoice::create(['owner_id' => $this->owner->id]);

    expect(fn () => $this->invoices->issue($empty))->toThrow(InvoiceException::class);
    expect($this->invoices->issue($this->invoices->forBooking($this->booking))->number)->toBe('INV-1405-000001');
});

it('answers 409 when editing an issued invoice', function () {
    $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));

    $this->withToken($this->staffToken)->postJson("/api/v1/staff/invoices/{$invoice->id}/lines", ['description_fa' => 'x', 'unit_price_toman' => 1])
        ->assertStatus(409);
});

it('blocks changes to issued invoices at the database', function (string $attack) {
    $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));
    $line = InvoiceLine::query()->where('invoice_id', $invoice->id)->first();

    expect(fn () => match ($attack) {
        'total' => $invoice->update(['total_rials' => 1]),
        'number' => $invoice->update(['number' => 'X']),
        'back to draft' => $invoice->update(['status' => 'draft']),
        'delete' => $invoice->delete(),
        'line edit' => $line->update(['quantity' => 9]),
        'line delete' => $line->delete(),
        'line add' => $invoice->lines()->create(['kind' => 'other', 'description_fa' => 'x', 'quantity' => 1, 'unit_price_rials' => 1]),
    })->toThrow(QueryException::class);
})->with(['total', 'number', 'back to draft', 'delete', 'line edit', 'line delete', 'line add']);

it('moves through part payment to paid', function () {
    $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));

    expect($this->invoices->recordPayment($invoice, 5_000_000)->status)->toBe(Invoice::PARTIALLY_PAID)
        ->and($this->invoices->recordPayment($invoice, 10_000_000)->status)->toBe(Invoice::PAID);
});

it('refuses overpayment, zero and payment of a draft', function () {
    $draft = $this->invoices->forBooking($this->booking);
    expect(fn () => $this->invoices->recordPayment($draft, 1))->toThrow(InvoiceException::class);

    $invoice = $this->invoices->issue($draft);
    expect(fn () => $this->invoices->recordPayment($invoice, 0))->toThrow(InvoiceException::class)
        ->and(fn () => $this->invoices->recordPayment($invoice, 15_000_001))->toThrow(InvoiceException::class);
});

it('cancels the original with a full credit note on its own sequence', function () {
    $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));

    $this->withToken($this->staffToken)->postJson("/api/v1/staff/invoices/{$invoice->id}/credit-notes")
        ->assertCreated()
        ->assertJsonPath('data.number', 'CN-1405-000001')
        ->assertJsonPath('data.credit_of_id', $invoice->id)
        ->assertJsonPath('data.total.rials', '15000000');

    expect($invoice->fresh()->status)->toBe(Invoice::CANCELLED);
});

it('credits one line and lowers what remains payable', function () {
    $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));
    $fee = $invoice->lines()->where('kind', InvoiceLine::HOME_VISIT_FEE)->first();

    $note = $this->invoices->creditNote($invoice, [$fee->id]);

    expect($note->total_rials)->toBe(5_000_000)
        ->and($invoice->fresh()->status)->toBe(Invoice::ISSUED)
        ->and($invoice->fresh()->credited_rials)->toBe(5_000_000)
        ->and($this->invoices->recordPayment($invoice->fresh(), 10_000_000)->status)->toBe(Invoice::PAID);
});

it('will not credit the same line twice', function () {
    $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));
    $fee = $invoice->lines()->where('kind', InvoiceLine::HOME_VISIT_FEE)->first();

    $this->invoices->creditNote($invoice, [$fee->id]);

    expect(fn () => $this->invoices->creditNote($invoice->fresh(), [$fee->id]))->toThrow(InvoiceException::class);
});

it('keeps invoices private and staff tools closed', function () {
    $invoice = $this->invoices->forBooking($this->booking);
    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;
    $vet = User::factory()->staff(Role::Vet)->create()->createToken('staff-spa', ['staff'])->plainTextToken;

    $this->withToken($this->ownerToken)->getJson("/api/v1/invoices/{$invoice->id}")->assertOk();
    $this->withToken($other)->getJson("/api/v1/invoices/{$invoice->id}")->assertNotFound();
    $this->withToken($other)->getJson('/api/v1/invoices')->assertJsonCount(0, 'data');
    $this->withToken($this->ownerToken)->postJson("/api/v1/staff/invoices/{$invoice->id}/issue")->assertForbidden();
    $this->withToken($vet)->postJson("/api/v1/staff/invoices/{$invoice->id}/issue")->assertForbidden();
});
