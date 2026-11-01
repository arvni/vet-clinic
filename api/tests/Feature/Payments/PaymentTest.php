<?php

use App\Models\User;
use App\Modules\Bookings\BookingPolicy;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Catalog\Models\Service;
use App\Modules\Identity\Role;
use App\Modules\Invoices\InvoiceService;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Gateways\FakePaymentGateway;
use App\Modules\Payments\Gateways\PaymentGateway;
use App\Modules\Payments\Gateways\PaymentUnavailableException;
use App\Modules\Payments\Gateways\ZarinpalGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentService;
use App\Modules\Pets\Models\Pet;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->ownerToken = $this->owner->createToken('owner-spa', ['owner'])->plainTextToken;
    $service = Service::create(['code' => 'visit', 'name_fa' => 'ویزیت', 'category' => 'visit', 'duration_minutes' => 30, 'locations' => ['home']]);
    $this->booking = Booking::create([
        'owner_id' => $this->owner->id,
        'pet_id' => Pet::factory()->create(['owner_id' => $this->owner->id])->id,
        'service_id' => $service->id,
        'vet_id' => User::factory()->staff(Role::Vet)->create()->id,
        'location' => 'home',
        'status' => Booking::PENDING_PAYMENT,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addMinutes(30),
        'service_price_rials' => 10_000_000,
        'home_visit_fee_rials' => 5_000_000,
    ]);
});

function startPayment($test): Payment
{
    $test->withToken($test->ownerToken)->postJson("/api/v1/bookings/{$test->booking->id}/pay")->assertOk();

    return Payment::query()->latest('id')->firstOrFail();
}

function callback($test, Payment $payment, string $status = 'OK')
{
    return $test->get("/api/v1/payments/callback?Authority={$payment->authority}&Status={$status}");
}

function gatewaySaying(array|Throwable $result): PaymentGateway
{
    return new class($result) extends FakePaymentGateway
    {
        public int $verifications = 0;

        public function __construct(private array|Throwable $result) {}

        public function verify(string $authority, int $amountRials): array
        {
            $this->verifications++;

            return $this->result instanceof Throwable ? throw $this->result : $this->result;
        }
    };
}

it('issues the invoice and sends the owner to the gateway', function () {
    $response = $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$this->booking->id}/pay")->assertOk();

    $payment = Payment::query()->firstOrFail();
    expect($payment->amount_rials)->toBe(15_000_000)
        ->and($payment->status)->toBe(Payment::PENDING)
        ->and($payment->invoice->status)->toBe(Invoice::ISSUED)
        ->and($response->json('data.redirect_url'))->toContain($payment->authority);
});

it('confirms the booking only after a verified payment', function () {
    $payment = startPayment($this);
    expect($this->booking->fresh()->status)->toBe(Booking::PENDING_PAYMENT);

    callback($this, $payment)->assertRedirect('/payments/result?status=paid');

    expect($this->booking->fresh()->status)->toBe(Booking::CONFIRMED)
        ->and($payment->fresh()->ref_id)->toBe('FAKE-REF')
        ->and($payment->invoice->fresh()->status)->toBe(Invoice::PAID);
});

it('applies a replayed callback once', function () {
    $gateway = gatewaySaying(['paid' => true, 'ref_id' => 'R1', 'card_pan' => null]);
    $this->instance(PaymentGateway::class, $gateway);
    $payment = startPayment($this);

    callback($this, $payment)->assertRedirect('/payments/result?status=paid');
    callback($this, $payment)->assertRedirect('/payments/result?status=paid');

    expect($gateway->verifications)->toBe(1)
        ->and($payment->invoice->fresh()->paid_rials)->toBe(15_000_000);
});

it('ignores a forged OK when the gateway says unpaid', function () {
    $this->instance(PaymentGateway::class, gatewaySaying(['paid' => false, 'ref_id' => null, 'card_pan' => null]));
    $payment = startPayment($this);

    callback($this, $payment)->assertRedirect('/payments/result?status=failed');

    expect($this->booking->fresh()->status)->toBe(Booking::PENDING_PAYMENT)
        ->and($payment->invoice->fresh()->paid_rials)->toBe(0);
});

it('marks a cancelled attempt failed without asking the gateway', function () {
    $gateway = gatewaySaying(['paid' => true, 'ref_id' => 'R', 'card_pan' => null]);
    $this->instance(PaymentGateway::class, $gateway);
    $payment = startPayment($this);

    callback($this, $payment, 'NOK')->assertRedirect('/payments/result?status=failed');

    expect($gateway->verifications)->toBe(0);
});

it('rejects an unknown authority', function () {
    $this->get('/api/v1/payments/callback?Authority=nope&Status=OK')->assertRedirect('/payments/result?status=failed');
});

it('keeps the payment pending when the gateway cannot answer', function () {
    $this->instance(PaymentGateway::class, gatewaySaying(new PaymentUnavailableException('down')));
    $payment = startPayment($this);

    callback($this, $payment)->assertRedirect('/payments/result?status=pending');

    expect($payment->fresh()->status)->toBe(Payment::PENDING);
});

it('flags money that arrives after the invoice was cancelled', function () {
    $payment = startPayment($this);
    app(InvoiceService::class)->creditNote($payment->invoice);

    callback($this, $payment)->assertRedirect('/payments/result?status=needs_review');

    expect($payment->fresh()->status)->toBe(Payment::NEEDS_REVIEW)
        ->and($payment->fresh()->ref_id)->toBe('FAKE-REF')
        ->and($this->booking->fresh()->status)->toBe(Booking::PENDING_PAYMENT);
});

it('flags a second payment for the same invoice', function () {
    $first = startPayment($this);
    $second = startPayment($this);

    callback($this, $first)->assertRedirect('/payments/result?status=paid');
    callback($this, $second)->assertRedirect('/payments/result?status=needs_review');
});

it('confirms at once when a discount leaves nothing to pay', function () {
    $invoices = app(InvoiceService::class);
    $invoices->applyStaffDiscount($invoices->forBooking($this->booking), 10000, null, 'x', $this->owner->id);

    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$this->booking->id}/pay")
        ->assertOk()->assertJsonPath('data.redirect_url', null);

    expect($this->booking->fresh()->status)->toBe(Booking::CONFIRMED)
        ->and(Payment::query()->count())->toBe(0)
        ->and(Invoice::query()->firstOrFail()->status)->toBe(Invoice::PAID);
});

it('refuses to pay someone else\'s booking or a settled one', function () {
    $other = User::factory()->create()->createToken('owner-spa', ['owner'])->plainTextToken;
    $this->withToken($other)->postJson("/api/v1/bookings/{$this->booking->id}/pay")->assertNotFound();

    $this->booking->update(['status' => Booking::CONFIRMED]);
    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$this->booking->id}/pay")->assertStatus(409);
});

it('refuses the fake gateway in production', function () {
    app()->forgetInstance(PaymentGateway::class);
    app()->detectEnvironment(fn () => 'production');

    $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$this->booking->id}/pay")->assertStatus(503);
    expect(Payment::query()->count())->toBe(0);

    app()->detectEnvironment(fn () => 'testing');
});

describe('Zarinpal', function () {
    it('requests in rials and builds the StartPay URL', function () {
        Http::fake(['*/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'A000123'], 'errors' => []])]);

        $result = (new ZarinpalGateway('m-1'))->request(15_000_000, 'https://x/cb', 'Invoice', '09120000000');

        expect($result)->toBe(['authority' => 'A000123', 'redirect_url' => 'https://payment.zarinpal.com/pg/StartPay/A000123']);
        Http::assertSent(fn ($r) => $r['merchant_id'] === 'm-1' && $r['amount'] === 15_000_000 && $r['currency'] === 'IRR');
    });

    it('raises on a refused request', function () {
        Http::fake(['*' => Http::response(['data' => [], 'errors' => ['code' => -9]], 422)]);

        (new ZarinpalGateway('m-1'))->request(1000, 'https://x/cb', 'x', '09120000000');
    })->throws(PaymentUnavailableException::class);

    it('treats 100 and 101 as paid and anything else as unpaid', function (int $code, bool $paid) {
        Http::fake(['*' => Http::response(['data' => ['code' => $code, 'ref_id' => 201, 'card_pan' => '5022-29**-****-1111'], 'errors' => []])]);

        expect((new ZarinpalGateway('m-1'))->verify('A1', 1000)['paid'])->toBe($paid);
    })->with([[100, true], [101, true], [-51, false]]);

    it('treats a refused verify as unpaid but a server error as unknown', function () {
        Http::fake(['*' => Http::sequence()
            ->push(['data' => [], 'errors' => ['code' => -50]], 422)
            ->push('down', 502)]);
        $gateway = new ZarinpalGateway('m-1');

        expect($gateway->verify('A1', 1000)['paid'])->toBeFalse()
            ->and(fn () => $gateway->verify('A1', 1000))->toThrow(PaymentUnavailableException::class);
    });
});

describe('cancelling', function () {
    beforeEach(function () {
        app(BookingPolicy::class)->save(['cancel_hours' => 0, 'reschedule_hours' => 0]);
    });

    function cancelBooking($test)
    {
        return $test->withToken($test->ownerToken)->postJson("/api/v1/bookings/{$test->booking->id}/cancel")->assertOk();
    }

    it('refunds everything paid before dispatch', function () {
        callback($this, startPayment($this));

        cancelBooking($this)->assertJsonPath('data.refund_due.rials', '15000000');

        expect(Invoice::query()->where('kind', 'invoice')->firstOrFail()->status)->toBe(Invoice::CANCELLED);
    });

    it('keeps the home-visit fee after dispatch', function () {
        callback($this, startPayment($this));
        $this->booking->update(['dispatched_at' => now()]);

        cancelBooking($this)->assertJsonPath('data.refund_due.rials', '10000000');

        expect(Invoice::query()->where('kind', 'credit_note')->firstOrFail()->total_rials)->toBe(10_000_000);
    });

    it('owes nothing on an unpaid booking and voids the invoice', function () {
        startPayment($this);

        cancelBooking($this)->assertJsonPath('data.refund_due.rials', '0');

        expect(Invoice::query()->where('kind', 'invoice')->firstOrFail()->status)->toBe(Invoice::CANCELLED);
    });

    it('cancels a draft invoice without a credit note', function () {
        app(InvoiceService::class)->forBooking($this->booking);

        cancelBooking($this);

        expect(Invoice::query()->where('kind', 'credit_note')->count())->toBe(0)
            ->and(Invoice::query()->firstOrFail()->status)->toBe(Invoice::CANCELLED);
    });

    it('cancels a booking that has no invoice', function () {
        cancelBooking($this)->assertJsonPath('data.status', 'cancelled');
    });
});

describe('reconciliation', function () {
    it('settles a stuck payment the owner really made', function () {
        $payment = startPayment($this);
        $this->travel(31)->minutes();

        $this->artisan('payments:reconcile')->assertSuccessful();

        expect($payment->fresh()->status)->toBe(Payment::PAID)
            ->and($this->booking->fresh()->status)->toBe(Booking::CONFIRMED);
    });

    it('leaves recent payments alone', function () {
        $payment = startPayment($this);
        $this->travel(5)->minutes();

        $this->artisan('payments:reconcile')->assertSuccessful();

        expect($payment->fresh()->status)->toBe(Payment::PENDING);
    });

    it('fails an abandoned payment and keeps trying when the gateway is down', function () {
        $this->instance(PaymentGateway::class, gatewaySaying(new PaymentUnavailableException('down')));
        app()->forgetInstance(PaymentService::class);
        $payment = startPayment($this);
        $this->travel(31)->minutes();

        $this->artisan('payments:reconcile')->assertSuccessful();
        expect($payment->fresh()->status)->toBe(Payment::PENDING);

        $this->instance(PaymentGateway::class, gatewaySaying(['paid' => false, 'ref_id' => null, 'card_pan' => null]));
        $this->artisan('payments:reconcile')->assertSuccessful();
        expect($payment->fresh()->status)->toBe(Payment::FAILED);
    });
});

describe('refunds', function () {
    beforeEach(function () {
        $this->adminToken = User::factory()->staff(Role::Admin)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
        app(BookingPolicy::class)->save(['cancel_hours' => 0, 'reschedule_hours' => 0]);
    });

    function refund($test, Payment $payment, int $toman)
    {
        return $test->withToken($test->adminToken)->postJson("/api/v1/admin/payments/{$payment->id}/refunds", [
            'toman' => $toman, 'reason' => 'لغو', 'reference' => 'BANK-1',
        ]);
    }

    it('lists what is owed and clears it as refunds are recorded', function () {
        $payment = startPayment($this);
        callback($this, $payment);
        $this->withToken($this->ownerToken)->postJson("/api/v1/bookings/{$this->booking->id}/cancel")->assertOk();

        $this->withToken($this->adminToken)->getJson('/api/v1/admin/refunds')
            ->assertOk()
            ->assertJsonPath('data.bookings.0.payment_id', $payment->id)
            ->assertJsonPath('data.bookings.0.amount.rials', '15000000');

        refund($this, $payment, 500_000)->assertCreated()->assertJsonPath('data.left.rials', '10000000');
        refund($this, $payment, 1_000_000)->assertCreated();

        $this->withToken($this->adminToken)->getJson('/api/v1/admin/refunds')->assertJsonCount(0, 'data.bookings');
        expect($this->booking->fresh()->refund_due_rials)->toBe(0);
    });

    it('never refunds more than was paid', function () {
        $payment = startPayment($this);
        callback($this, $payment);

        refund($this, $payment, 1_500_001)->assertStatus(409);
        refund($this, $payment, 1_500_000)->assertCreated();
        refund($this, $payment, 1)->assertStatus(409);
    });

    it('refuses to refund an unpaid attempt', function () {
        refund($this, startPayment($this), 1)->assertStatus(409);
    });

    it('closes a needs-review payment once fully returned', function () {
        $first = startPayment($this);
        $second = startPayment($this);
        callback($this, $first);
        callback($this, $second);

        $this->withToken($this->adminToken)->getJson('/api/v1/admin/refunds')->assertJsonPath('data.needs_review.0.payment_id', $second->id);

        refund($this, $second, 1_500_000)->assertCreated();

        expect($second->fresh()->status)->toBe(Payment::REFUNDED);
        $this->withToken($this->adminToken)->getJson('/api/v1/admin/refunds')->assertJsonCount(0, 'data.needs_review');
    });

    it('keeps refunds admin-only', function () {
        $vet = User::factory()->staff(Role::Dispatcher)->create()->createToken('staff-spa', ['staff'])->plainTextToken;
        $this->withToken($vet)->getJson('/api/v1/admin/refunds')->assertForbidden();
    });
});
