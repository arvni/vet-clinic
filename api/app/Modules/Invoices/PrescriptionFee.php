<?php

declare(strict_types=1);

namespace App\Modules\Invoices;

use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\Setting;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;

/** A prescription from an online consult adds its admin-set fee (FR-5.6). */
final class PrescriptionFee
{
    private const KEY = 'prescription_fee';

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly TaxRates $tax,
    ) {}

    public function rials(): ?int
    {
        $setting = Setting::query()->where('key', self::KEY)->first();

        return $setting === null ? null : (int) $setting->value['rials'];
    }

    public function save(int $rials): void
    {
        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => ['rials' => $rials]]);
    }

    public function charge(Booking $booking): Invoice
    {
        $fee = $this->rials();
        if ($fee === null) {
            throw InvoiceException::feeNotSet();
        }
        if ($booking->service->category !== 'consult' || $booking->status === Booking::CANCELLED) {
            throw InvoiceException::notConsult();
        }

        return DB::transaction(function () use ($booking, $fee): Invoice {
            $invoice = $this->invoices->forBooking($booking);

            // Serialises two clicks on one booking.
            Invoice::query()->whereKey($invoice->id)->lockForUpdate()->first();

            $charged = InvoiceLine::query()
                ->where('kind', InvoiceLine::PRESCRIPTION)
                ->whereIn('invoice_id', Invoice::query()->where('id', $invoice->id)->orWhere('supplement_of_id', $invoice->id)->select('id'))
                ->exists();
            if ($charged) {
                throw InvoiceException::alreadyCharged();
            }

            $line = [
                'kind' => InvoiceLine::PRESCRIPTION,
                'description_fa' => 'هزینه نسخه',
                'description_en' => 'Prescription fee',
                'quantity' => 1,
                'unit_price_rials' => $fee,
                'vat_basis_points' => $this->tax->basisPoints(null),
            ];

            if ($invoice->isDraft()) {
                return $this->invoices->addLine($invoice, $line);
            }

            $supplement = Invoice::create(['owner_id' => $invoice->owner_id, 'supplement_of_id' => $invoice->id]);

            return $this->invoices->issue($this->invoices->addLine($supplement, $line));
        });
    }
}
