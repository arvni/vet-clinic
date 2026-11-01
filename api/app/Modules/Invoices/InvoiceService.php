<?php

declare(strict_types=1);

namespace App\Modules\Invoices;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceLine;
use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Invoice life cycle (FR-5.4): draft → issued → paid / partially paid → cancelled. */
final class InvoiceService
{
    /** The booking's invoice, created on first use with its price snapshots. */
    public function forBooking(Booking $booking): Invoice
    {
        $existing = Invoice::query()
            ->where('booking_id', $booking->id)
            ->where('kind', Invoice::KIND_INVOICE)
            ->where('status', '<>', Invoice::CANCELLED)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($booking): Invoice {
            $invoice = Invoice::create(['owner_id' => $booking->owner_id, 'booking_id' => $booking->id]);

            if ($booking->service_price_rials !== null) {
                $invoice->lines()->create([
                    'kind' => InvoiceLine::SERVICE,
                    'service_id' => $booking->service_id,
                    'description_fa' => $booking->service->name_fa,
                    'description_en' => $booking->service->name_en,
                    'quantity' => 1,
                    'unit_price_rials' => $booking->service_price_rials,
                ]);
            }
            if ($booking->home_visit_fee_rials > 0) {
                $invoice->lines()->create([
                    'kind' => InvoiceLine::HOME_VISIT_FEE,
                    'description_fa' => 'هزینه ویزیت در منزل',
                    'description_en' => 'Home-visit fee',
                    'quantity' => 1,
                    'unit_price_rials' => $booking->home_visit_fee_rials,
                ]);
            }

            return $this->recalculate($invoice);
        });
    }

    /** @param  array<string, mixed>  $attributes */
    public function addLine(Invoice $invoice, array $attributes): Invoice
    {
        $this->assertDraft($invoice);

        return DB::transaction(function () use ($invoice, $attributes): Invoice {
            $invoice->lines()->create($attributes);

            return $this->recalculate($invoice);
        });
    }

    public function removeLine(Invoice $invoice, InvoiceLine $line): Invoice
    {
        $this->assertDraft($invoice);

        return DB::transaction(function () use ($invoice, $line): Invoice {
            $line->delete();

            return $this->recalculate($invoice);
        });
    }

    public function issue(Invoice $invoice): Invoice
    {
        $this->assertDraft($invoice);
        if (! $invoice->lines()->exists()) {
            throw InvoiceException::empty();
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $this->recalculate($invoice);
            $invoice->update([
                'number' => $this->nextNumber($invoice->kind === Invoice::KIND_CREDIT_NOTE ? 'CN' : 'INV'),
                'status' => Invoice::ISSUED,
                'issued_at' => now(),
            ]);

            return $invoice;
        });
    }

    /** Applies money received; payment gateways call this after verification. */
    public function recordPayment(Invoice $invoice, int $rials): Invoice
    {
        if (! in_array($invoice->status, [Invoice::ISSUED, Invoice::PARTIALLY_PAID], true)) {
            throw InvoiceException::notPayable();
        }

        $paid = $invoice->paid_rials + $rials;
        if ($rials <= 0 || $paid > $invoice->total_rials - $invoice->credited_rials) {
            throw InvoiceException::badAmount();
        }

        $invoice->update([
            'paid_rials' => $paid,
            'status' => $paid === $invoice->total_rials - $invoice->credited_rials ? Invoice::PAID : Invoice::PARTIALLY_PAID,
        ]);

        return $invoice;
    }

    /**
     * Corrects an issued invoice with a credit note copying the given lines.
     * Crediting everything cancels the original.
     *
     * @param  list<int>  $lineIds  empty = every line
     */
    public function creditNote(Invoice $invoice, array $lineIds = []): Invoice
    {
        if ($invoice->kind !== Invoice::KIND_INVOICE || $invoice->isDraft() || $invoice->status === Invoice::CANCELLED) {
            throw InvoiceException::notCreditable();
        }

        $lines = $invoice->lines()->get()
            ->when($lineIds !== [], fn ($all) => $all->whereIn('id', $lineIds));
        if ($lines->isEmpty()) {
            throw InvoiceException::empty();
        }
        if (InvoiceLine::query()->whereIn('credits_line_id', $lines->modelKeys())->exists()) {
            throw InvoiceException::notCreditable();
        }

        return DB::transaction(function () use ($invoice, $lines): Invoice {
            $note = Invoice::create([
                'kind' => Invoice::KIND_CREDIT_NOTE,
                'credit_of_id' => $invoice->id,
                'owner_id' => $invoice->owner_id,
            ]);
            foreach ($lines as $line) {
                $note->lines()->create([
                    ...$line->only([
                        'kind', 'service_id', 'description_fa', 'description_en',
                        'quantity', 'unit_price_rials', 'vat_basis_points',
                    ]),
                    'credits_line_id' => $line->id,
                ]);
            }
            $this->issue($note);

            $credited = $invoice->credited_rials + $note->total_rials;
            if ($credited > $invoice->total_rials) {
                throw InvoiceException::badAmount();
            }

            $invoice->update([
                'credited_rials' => $credited,
                'status' => $credited === $invoice->total_rials ? Invoice::CANCELLED : $invoice->status,
            ]);

            return $note;
        });
    }

    private function recalculate(Invoice $invoice): Invoice
    {
        $subtotal = 0;
        $vat = 0;
        foreach ($invoice->lines()->get() as $line) {
            $subtotal += $line->amountRials();
            $vat += Money::rials($line->amountRials())->percent($line->vat_basis_points)->rials;
        }

        $invoice->update(['subtotal_rials' => $subtotal, 'vat_rials' => $vat, 'total_rials' => $subtotal + $vat]);

        return $invoice;
    }

    /** Runs inside the issuing transaction: the row lock keeps the sequence gapless. */
    private function nextNumber(string $prefix): string
    {
        $year = JalaliDate::format(now(), 'Y', 'en');
        $key = "{$prefix}-{$year}";

        $row = DB::selectOne(
            'INSERT INTO invoice_counters (key, last) VALUES (?, 1)
             ON CONFLICT (key) DO UPDATE SET last = invoice_counters.last + 1 RETURNING last',
            [$key],
        );

        return sprintf('%s-%06d', $key, $row->last);
    }

    private function assertDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw InvoiceException::notDraft();
        }
    }
}
