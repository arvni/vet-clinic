<?php

declare(strict_types=1);

namespace App\Modules\Bookings;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Invoices\InvoiceService;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a booking and its money together. Everything is credited, except
 * the home-visit fee once someone has left for the visit (FR-3.5).
 */
final class BookingCancellation
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function cancel(Booking $booking, ?string $reason): Booking
    {
        return DB::transaction(function () use ($booking, $reason): Booking {
            $booking->update([
                'refund_due_rials' => $this->reverseInvoice($booking),
                'status' => Booking::CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $booking;
        });
    }

    /** @return int rials owed back to the owner */
    private function reverseInvoice(Booking $booking): int
    {
        $invoice = Invoice::query()
            ->where('booking_id', $booking->id)
            ->where('kind', Invoice::KIND_INVOICE)
            ->where('status', '<>', Invoice::CANCELLED)
            ->lockForUpdate()
            ->first();
        if ($invoice === null) {
            return 0;
        }

        if ($invoice->isDraft()) {
            $invoice->update(['status' => Invoice::CANCELLED]);

            return 0;
        }

        $lines = $invoice->lines()->get();
        $credited = $booking->dispatched_at === null
            ? $lines
            : $lines->where('kind', '<>', InvoiceLine::HOME_VISIT_FEE);
        if ($credited->isEmpty()) {
            return 0;
        }

        $note = $this->invoices->creditNote($invoice, $credited->modelKeys());
        $kept = $invoice->total_rials - $note->total_rials;

        return max(0, $invoice->paid_rials - $kept);
    }
}
