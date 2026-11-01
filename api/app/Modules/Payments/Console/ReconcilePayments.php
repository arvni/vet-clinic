<?php

declare(strict_types=1);

namespace App\Modules\Payments\Console;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentService;
use Illuminate\Console\Command;
use Throwable;

/** Settles payments whose owner never came back from the bank (T036). */
class ReconcilePayments extends Command
{
    /** Long enough that nobody is still typing their card details. */
    private const GRACE_MINUTES = 30;

    protected $signature = 'payments:reconcile';

    protected $description = 'Ask the gateway about payments stuck in pending';

    public function handle(PaymentService $payments): int
    {
        $stuck = Payment::query()
            ->where('status', Payment::PENDING)
            ->where('created_at', '<', now()->subMinutes(self::GRACE_MINUTES))
            ->orderBy('id')
            ->limit(200)
            ->pluck('authority');

        foreach ($stuck as $authority) {
            try {
                $this->line($authority.': '.$payments->settle($authority, true));
            } catch (Throwable $e) {
                // One bad row must not hold up the rest.
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
