<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController
{
    public function pay(Request $request, Booking $booking, PaymentService $payments): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();
        abort_unless($booking->owner_id === $owner->id, 404);

        if ($booking->status !== Booking::PENDING_PAYMENT) {
            return response()->json(['message' => __('payments.not_due')], 409);
        }

        return response()->json(['data' => $payments->payBooking($booking, $owner)]);
    }

    /** For invoices with no booking of their own, such as a prescription fee. */
    public function payInvoice(Request $request, Invoice $invoice, PaymentService $payments): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();
        abort_unless($invoice->owner_id === $owner->id, 404);

        if (! in_array($invoice->status, [Invoice::ISSUED, Invoice::PARTIALLY_PAID], true) || $invoice->kind !== Invoice::KIND_INVOICE) {
            return response()->json(['message' => __('payments.not_due')], 409);
        }

        return response()->json(['data' => $payments->payInvoice($invoice, $owner)]);
    }

    /** The gateway sends the browser here; the SPA shows the outcome. */
    public function callback(Request $request, PaymentService $payments): RedirectResponse
    {
        $authority = (string) $request->query('Authority', '');
        $status = $authority === '' ? Payment::FAILED : $payments->settle($authority, $request->query('Status') === 'OK');

        return redirect('/payments/result?status='.$status);
    }
}
