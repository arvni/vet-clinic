<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Http\Controllers;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Catalog\Models\Service;
use App\Modules\Invoices\InvoiceService;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceLine;
use App\Modules\Invoices\PrescriptionFee;
use App\Modules\Invoices\TaxRates;
use App\Support\Digits;
use App\Support\Money;
use App\Support\PersianText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Owners read their invoices; dispatchers and admins build and issue them. */
class InvoiceController
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly TaxRates $tax,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::query()->where('owner_id', $this->user($request)->id)->with('lines')->latest('id')->limit(100)->get();

        return response()->json(['data' => $invoices->map(fn (Invoice $i) => $this->data($i))]);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->owner_id === $this->user($request)->id, 404);

        return response()->json(['data' => $this->data($invoice)]);
    }

    public function forBooking(Booking $booking): JsonResponse
    {
        Gate::authorize('manage-invoices');

        return response()->json(['data' => $this->data($this->invoices->forBooking($booking))]);
    }

    public function addLine(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('manage-invoices');

        if ($request->filled('unit_price_toman')) {
            $request->merge(['unit_price_toman' => Digits::toLatin(str_replace([',', '٬', ' '], '', (string) $request->input('unit_price_toman')))]);
        }
        if ($request->filled('description_fa')) {
            $request->merge(['description_fa' => PersianText::normalize((string) $request->input('description_fa'))]);
        }
        $data = $request->validate([
            'service_id' => ['required_without:description_fa', 'nullable', 'integer', 'exists:services,id'],
            'description_fa' => ['required_without:service_id', 'nullable', 'string', 'max:255'],
            'description_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit_price_toman' => ['required_without:service_id', 'nullable', 'integer', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'between:1,1000'],
        ]);

        return response()->json(['data' => $this->data($this->invoices->addLine($invoice, $this->lineAttributes($data)))], 201);
    }

    public function removeLine(Invoice $invoice, InvoiceLine $line): JsonResponse
    {
        Gate::authorize('manage-invoices');
        abort_unless($line->invoice_id === $invoice->id, 404);

        return response()->json(['data' => $this->data($this->invoices->removeLine($invoice, $line))]);
    }

    public function prescriptionFee(Booking $booking, PrescriptionFee $fee): JsonResponse
    {
        Gate::authorize('prescribe');

        return response()->json(['data' => $this->data($fee->charge($booking))], 201);
    }

    public function applyCode(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->owner_id === $this->user($request)->id, 404);
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        return response()->json(['data' => $this->data($this->invoices->applyCode($invoice, $data['code']))]);
    }

    public function removeCode(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($invoice->owner_id === $this->user($request)->id, 404);

        if ($invoice->discount_code_id === null) {
            return response()->json(['data' => $this->data($invoice)]);
        }

        return response()->json(['data' => $this->data($this->invoices->removeDiscount($invoice))]);
    }

    public function staffDiscount(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('manage-invoices');

        if ($request->filled('toman')) {
            $request->merge(['toman' => Digits::toLatin(str_replace([',', '٬', ' '], '', (string) $request->input('toman')))]);
        }
        $data = $request->validate([
            'percent' => ['required_without:toman', 'prohibits:toman', 'nullable', 'numeric', 'gt:0', 'max:100'],
            'toman' => ['required_without:percent', 'nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $this->data($this->invoices->applyStaffDiscount(
            $invoice,
            isset($data['percent']) ? (int) round((float) $data['percent'] * 100) : null,
            isset($data['toman']) ? (int) $data['toman'] * 10 : null,
            $data['reason'],
            $this->user($request)->id,
        ))]);
    }

    public function removeStaffDiscount(Invoice $invoice): JsonResponse
    {
        Gate::authorize('manage-invoices');

        return response()->json(['data' => $this->data($this->invoices->removeDiscount($invoice))]);
    }

    public function issue(Invoice $invoice): JsonResponse
    {
        Gate::authorize('manage-invoices');

        return response()->json(['data' => $this->data($this->invoices->issue($invoice))]);
    }

    public function creditNote(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('manage-invoices');

        $data = $request->validate([
            'line_ids' => ['sometimes', 'array'],
            'line_ids.*' => ['integer'],
        ]);

        return response()->json(['data' => $this->data($this->invoices->creditNote($invoice, array_map('intval', $data['line_ids'] ?? [])))], 201);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function lineAttributes(array $data): array
    {
        $quantity = (int) ($data['quantity'] ?? 1);

        if (isset($data['service_id'])) {
            $service = Service::query()->findOrFail($data['service_id']);
            $price = isset($data['unit_price_toman']) ? (int) $data['unit_price_toman'] * 10 : ($service->priceAt()->rials ?? 0);

            return [
                'kind' => InvoiceLine::SERVICE,
                'service_id' => $service->id,
                'description_fa' => $service->name_fa,
                'description_en' => $service->name_en,
                'quantity' => $quantity,
                'unit_price_rials' => $price,
                'vat_basis_points' => $this->tax->basisPoints($service->tax_category),
            ];
        }

        return [
            'kind' => InvoiceLine::OTHER,
            'description_fa' => $data['description_fa'],
            'description_en' => $data['description_en'] ?? null,
            'quantity' => $quantity,
            'unit_price_rials' => (int) $data['unit_price_toman'] * 10,
            'vat_basis_points' => $this->tax->basisPoints(null),
        ];
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** @return array<string, mixed> */
    private function data(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'kind' => $invoice->kind,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'booking_id' => $invoice->booking_id,
            'credit_of_id' => $invoice->credit_of_id,
            'supplement_of_id' => $invoice->supplement_of_id,
            'issued_at' => $invoice->issued_at?->toIso8601ZuluString(),
            'subtotal' => Money::rials($invoice->subtotal_rials),
            'discount' => Money::rials($invoice->discount_rials),
            'vat' => Money::rials($invoice->vat_rials),
            'total' => Money::rials($invoice->total_rials),
            'paid' => Money::rials($invoice->paid_rials),
            'credited' => Money::rials($invoice->credited_rials),
            'lines' => $invoice->lines()->get()->map(fn (InvoiceLine $l) => [
                'id' => $l->id,
                'kind' => $l->kind,
                'description' => $l->translated('description'),
                'quantity' => $l->quantity,
                'unit_price' => Money::rials($l->unit_price_rials),
                'amount' => Money::rials($l->amountRials()),
                'discount' => Money::rials($l->discount_rials),
                'vat_percent' => $l->vat_basis_points / 100,
                'vat' => Money::rials($l->vatRials()),
            ]),
        ];
    }
}
