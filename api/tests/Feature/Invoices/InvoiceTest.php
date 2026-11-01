<?php

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Role;
use App\Modules\Invoices\InvoiceException;
use App\Modules\Invoices\InvoiceService;
use App\Modules\Invoices\Models\DiscountCode;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceLine;
use App\Modules\Invoices\Models\TaxRate;
use App\Modules\Invoices\TaxRates;
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

describe('pricing', function () {
    beforeEach(function () {
        TaxRate::create(['category' => 'general', 'basis_points' => 1000, 'effective_from' => '2026-03-21']);
        $this->adminToken = User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
    });

    it('charges VAT per line by tax category', function () {
        TaxRate::create(['category' => 'exempt_med', 'basis_points' => 0, 'effective_from' => '2026-03-21']);
        $this->service->update(['tax_category' => 'exempt_med']);

        $invoice = app(InvoiceService::class)->forBooking($this->booking->fresh());

        // Exempt visit, 10% on the 5,000,000 home-visit fee.
        expect($invoice->vat_rials)->toBe(500_000)->and($invoice->total_rials)->toBe(15_500_000);
    });

    it('falls back to the general rate and uses the rate in force', function () {
        TaxRate::create(['category' => 'general', 'basis_points' => 1200, 'effective_from' => '2027-03-21']);
        $rates = app(TaxRates::class);

        expect($rates->basisPoints('unknown'))->toBe(1000)
            ->and($rates->basisPoints(null, Carbon::parse('2027-04-01')))->toBe(1200)
            ->and($rates->basisPoints(null, Carbon::parse('2026-01-01')))->toBe(0);
    });

    it('keeps the VAT of an issued invoice when the rate changes', function () {
        $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));

        TaxRate::create(['category' => 'general', 'basis_points' => 2000, 'effective_from' => '2026-11-01']);

        expect($invoice->fresh()->vat_rials)->toBe(1_500_000);
    });

    it('recalculates VAT and total as a line is added', function () {
        $id = $this->invoices->forBooking($this->booking)->id;

        $this->withToken($this->staffToken)->postJson("/api/v1/staff/invoices/{$id}/lines", ['description_fa' => 'دارو', 'unit_price_toman' => 100_000])
            ->assertJsonPath('data.vat.rials', '1600000')
            ->assertJsonPath('data.total.rials', '17600000')
            ->assertJsonPath('data.lines.2.vat_percent', 10);
    });

    it('applies a code before VAT, spread over the lines', function () {
        DiscountCode::create(['code' => 'VET10', 'basis_points' => 1000]);
        $id = $this->invoices->forBooking($this->booking)->id;

        $this->withToken($this->ownerToken)->postJson("/api/v1/invoices/{$id}/discount-code", ['code' => ' vet10 '])
            ->assertOk()
            ->assertJsonPath('data.discount.rials', '1500000')
            ->assertJsonPath('data.vat.rials', '1350000')
            ->assertJsonPath('data.total.rials', '14850000')
            ->assertJsonPath('data.lines.0.discount.rials', '1000000')
            ->assertJsonPath('data.lines.1.discount.rials', '500000');
    });

    it('keeps a percent discount in step with later line changes', function () {
        DiscountCode::create(['code' => 'VET10', 'basis_points' => 1000]);
        $invoice = $this->invoices->applyCode($this->invoices->forBooking($this->booking), 'VET10');

        $this->invoices->addLine($invoice, ['kind' => 'other', 'description_fa' => 'x', 'quantity' => 1, 'unit_price_rials' => 5_000_000, 'vat_basis_points' => 1000]);

        expect($invoice->fresh()->discount_rials)->toBe(2_000_000);
    });

    it('caps a fixed discount at the subtotal', function () {
        $invoice = $this->invoices->applyStaffDiscount($this->invoices->forBooking($this->booking), null, 99_000_000, 'x', User::factory()->create()->id);

        expect($invoice->discount_rials)->toBe(15_000_000)->and($invoice->total_rials)->toBe(0);
    });

    it('refuses dead codes', function (array $attributes) {
        DiscountCode::create(['code' => 'DEAD', 'basis_points' => 1000, ...$attributes]);
        $id = $this->invoices->forBooking($this->booking)->id;

        $this->withToken($this->ownerToken)->postJson("/api/v1/invoices/{$id}/discount-code", ['code' => 'DEAD'])->assertStatus(409);
    })->with([
        'inactive' => [['active' => false]],
        'expired' => [['ends_at' => '2026-01-01']],
        'not started' => [['starts_at' => '2027-01-01']],
    ]);

    it('spends a limited code at issue, not when applied', function () {
        $code = DiscountCode::create(['code' => 'ONCE', 'basis_points' => 1000, 'max_uses' => 1]);

        $first = $this->invoices->applyCode($this->invoices->forBooking($this->booking), 'ONCE');
        $second = $this->invoices->applyCode($this->invoices->forBooking(invoiceBooking($this)), 'ONCE');
        expect($code->fresh()->used_count)->toBe(0);

        $this->invoices->issue($first);
        expect($code->fresh()->used_count)->toBe(1)
            ->and(fn () => $this->invoices->issue($second))->toThrow(InvoiceException::class);
    });

    it('records who gave a staff discount and why', function () {
        $id = $this->invoices->forBooking($this->booking)->id;

        $this->withToken($this->staffToken)->postJson("/api/v1/staff/invoices/{$id}/discount", ['percent' => 20])->assertUnprocessable();
        $this->withToken($this->staffToken)->postJson("/api/v1/staff/invoices/{$id}/discount", ['percent' => 20, 'toman' => 1, 'reason' => 'x'])->assertUnprocessable();
        $this->withToken($this->staffToken)->postJson("/api/v1/staff/invoices/{$id}/discount", ['percent' => 20, 'reason' => 'مشتری قدیمی'])
            ->assertOk()->assertJsonPath('data.discount.rials', '3000000');

        $invoice = Invoice::findOrFail($id);
        expect($invoice->discount_reason)->toBe('مشتری قدیمی')->and($invoice->discounted_by)->not->toBeNull();

        $this->withToken($this->staffToken)->deleteJson("/api/v1/staff/invoices/{$id}/discount")->assertJsonPath('data.discount.rials', '0');
    });

    it('stops owners removing a staff discount or touching others', function () {
        $invoice = $this->invoices->applyStaffDiscount($this->invoices->forBooking($this->booking), 2000, null, 'x', User::factory()->create()->id);
        $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;

        $this->withToken($this->ownerToken)->deleteJson("/api/v1/invoices/{$invoice->id}/discount-code")->assertJsonPath('data.discount.rials', '3000000');
        $this->withToken($other)->postJson("/api/v1/invoices/{$invoice->id}/discount-code", ['code' => 'X'])->assertNotFound();
    });

    it('credits a discounted invoice back to exactly its total', function () {
        DiscountCode::create(['code' => 'VET10', 'basis_points' => 1000]);
        $invoice = $this->invoices->issue($this->invoices->applyCode($this->invoices->forBooking($this->booking), 'VET10'));

        $note = $this->invoices->creditNote($invoice);

        expect($note->total_rials)->toBe($invoice->total_rials)
            ->and($note->discount_rials)->toBe(1_500_000)
            ->and($invoice->fresh()->status)->toBe(Invoice::CANCELLED);
    });

    it('freezes the discount on issue', function () {
        $invoice = $this->invoices->issue($this->invoices->forBooking($this->booking));

        expect(fn () => $this->invoices->removeDiscount($invoice->fresh()))->toThrow(InvoiceException::class)
            ->and(fn () => $invoice->update(['discount_rials' => 1]))->toThrow(QueryException::class);
    });

    it('lets admins manage rates and codes', function () {
        $this->withToken($this->adminToken)->postJson('/api/v1/admin/tax-rates', ['category' => 'general', 'percent' => 12, 'effective_from' => '2027-03-21'])
            ->assertCreated()->assertJsonPath('data.percent', 12);
        $this->withToken($this->adminToken)->postJson('/api/v1/admin/tax-rates', ['category' => 'general', 'percent' => 9, 'effective_from' => '2027-03-21'])
            ->assertUnprocessable();

        $id = $this->withToken($this->adminToken)->postJson('/api/v1/admin/discount-codes', ['code' => 'nowruz', 'toman' => 50000, 'max_uses' => 100])
            ->assertCreated()->assertJsonPath('data.code', 'NOWRUZ')->assertJsonPath('data.fixed_rials', '500000')->json('data.id');

        $this->withToken($this->adminToken)->patchJson("/api/v1/admin/discount-codes/{$id}", ['active' => false])->assertJsonPath('data.active', false);
        $this->withToken($this->staffToken)->getJson('/api/v1/admin/tax-rates')->assertForbidden();
    });
});
