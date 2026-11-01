<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Invoices\InvoiceException;
use App\Modules\Invoices\InvoiceService;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Gateways\PaymentGateway;
use App\Modules\Payments\Gateways\PaymentUnavailableException;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Takes a booking from unpaid to confirmed (FR-3.3, T035, T037). */
final class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly InvoiceService $invoices,
    ) {}

    /** @return array{redirect_url: string|null} null when nothing is owed */
    public function payBooking(Booking $booking, User $owner): array
    {
        return $this->payInvoice($this->invoices->forBooking($booking), $owner);
    }

    /** @return array{redirect_url: string|null} null when nothing is owed */
    public function payInvoice(Invoice $invoice, User $owner): array
    {
        if ($invoice->isDraft()) {
            $this->invoices->issue($invoice);
        }

        $due = $invoice->total_rials - $invoice->credited_rials - $invoice->paid_rials;
        if ($due <= 0) {
            $this->invoices->closeIfNothingDue($invoice);
            $this->settled($invoice);

            return ['redirect_url' => null];
        }

        $request = $this->gateway->request(
            $due,
            url('/api/v1/payments/callback'),
            'Invoice '.$invoice->number,
            $owner->mobile,
        );

        Payment::create([
            'invoice_id' => $invoice->id,
            'owner_id' => $owner->id,
            'gateway' => $this->gateway->name(),
            'authority' => $request['authority'],
            'amount_rials' => $due,
            'status' => Payment::PENDING,
        ]);

        return ['redirect_url' => $request['redirect_url']];
    }

    /**
     * Settles a gateway callback. The row lock serialises replays, and only a
     * server-side verify counts: the browser's status merely says who to ask.
     */
    public function settle(string $authority, bool $browserSaysOk): string
    {
        return DB::transaction(function () use ($authority, $browserSaysOk): string {
            $payment = Payment::query()->where('authority', $authority)->lockForUpdate()->first();
            if ($payment === null) {
                return Payment::FAILED;
            }
            if ($payment->status !== Payment::PENDING) {
                return $payment->status;
            }
            if (! $browserSaysOk) {
                $payment->update(['status' => Payment::FAILED]);

                return Payment::FAILED;
            }

            try {
                $result = $this->gateway->verify($authority, $payment->amount_rials);
            } catch (PaymentUnavailableException $e) {
                // Unknown, not failed: the owner may well have paid. Reconciliation decides.
                report($e);

                return Payment::PENDING;
            }

            if (! $result['paid']) {
                $payment->update(['status' => Payment::FAILED]);

                return Payment::FAILED;
            }

            return $this->apply($payment, $result['ref_id'], $result['card_pan']);
        });
    }

    private function apply(Payment $payment, ?string $refId, ?string $cardPan): string
    {
        $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);
        $status = Payment::PAID;

        try {
            $this->invoices->recordPayment($invoice, $payment->amount_rials);
        } catch (InvoiceException $e) {
            // Cancelled or paid twice while the owner was at the bank.
            Log::error('Payment needs review', ['payment_id' => $payment->id, 'reason' => $e->getMessage()]);
            $status = Payment::NEEDS_REVIEW;
        }

        $payment->update(['status' => $status, 'ref_id' => $refId, 'card_pan' => $cardPan, 'paid_at' => now()]);

        if ($invoice->status === Invoice::PAID) {
            $this->settled($invoice);
        }

        return $status;
    }

    private function settled(Invoice $invoice): void
    {
        Booking::query()
            ->whereKey($invoice->booking_id)
            ->where('status', Booking::PENDING_PAYMENT)
            ->first()
            ?->update(['status' => Booking::CONFIRMED]);
    }
}
