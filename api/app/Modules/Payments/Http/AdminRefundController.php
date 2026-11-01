<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http;

use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use App\Support\Digits;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** What is owed back, and the record of paying it. */
class AdminRefundController
{
    public function index(): JsonResponse
    {
        Gate::authorize('manage-refunds');

        $review = Payment::query()->where('status', Payment::NEEDS_REVIEW)->get();
        $owed = Booking::query()->where('refund_due_rials', '>', 0)->get();

        return response()->json(['data' => [
            'needs_review' => $review->map(fn (Payment $p) => [
                'payment_id' => $p->id,
                'owner_id' => $p->owner_id,
                'amount' => Money::rials($p->amount_rials - $this->refunded($p)),
                'ref_id' => $p->ref_id,
            ]),
            'bookings' => $owed->map(fn (Booking $b) => [
                'booking_id' => $b->id,
                'owner_id' => $b->owner_id,
                'payment_id' => $this->paymentFor($b)?->id,
                'amount' => Money::rials($b->refund_due_rials),
            ]),
        ]]);
    }

    public function store(Request $request, Payment $payment): JsonResponse
    {
        Gate::authorize('manage-refunds');

        if ($request->filled('toman')) {
            $request->merge(['toman' => Digits::toLatin(str_replace([',', '٬', ' '], '', (string) $request->input('toman')))]);
        }
        $data = $request->validate([
            'toman' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:64'],
        ]);
        $rials = (int) $data['toman'] * 10;

        /** @var User $admin */
        $admin = $request->user();

        return DB::transaction(function () use ($payment, $data, $rials, $admin): JsonResponse {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $left = $locked->amount_rials - $this->refunded($locked);
            if (! in_array($locked->status, [Payment::PAID, Payment::NEEDS_REVIEW], true) || $rials > $left) {
                return response()->json(['message' => __('payments.refund_too_large')], 409);
            }

            Refund::create([
                'payment_id' => $locked->id,
                'amount_rials' => $rials,
                'reason' => $data['reason'],
                'reference' => $data['reference'],
                'created_by' => $admin->id,
            ]);

            if ($locked->status === Payment::NEEDS_REVIEW && $rials === $left) {
                $locked->update(['status' => Payment::REFUNDED]);
            }

            $booking = Booking::query()->whereKey(Invoice::query()->whereKey($locked->invoice_id)->value('booking_id'))->first();
            if ($booking !== null && $booking->refund_due_rials > 0) {
                $booking->update(['refund_due_rials' => max(0, $booking->refund_due_rials - $rials)]);
            }

            return response()->json(['data' => ['payment_id' => $locked->id, 'left' => Money::rials($left - $rials)]], 201);
        });
    }

    private function refunded(Payment $payment): int
    {
        return (int) Refund::query()->where('payment_id', $payment->id)->sum('amount_rials');
    }

    private function paymentFor(Booking $booking): ?Payment
    {
        return Payment::query()
            ->whereIn('invoice_id', Invoice::query()->where('booking_id', $booking->id)->select('id'))
            ->where('status', Payment::PAID)
            ->latest('id')
            ->first();
    }
}
