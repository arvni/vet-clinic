<?php

declare(strict_types=1);

namespace App\Modules\Invoices;

use App\Modules\Invoices\Models\TaxRate;
use Illuminate\Support\Carbon;

/** VAT by tax category (FR-5.3). A service with no category pays the general rate. */
final class TaxRates
{
    public const GENERAL = 'general';

    public function basisPoints(?string $category, ?Carbon $at = null): int
    {
        $date = ($at ?? now())->toDateString();

        foreach (array_unique([$category ?? self::GENERAL, self::GENERAL]) as $candidate) {
            $rate = TaxRate::query()
                ->where('category', $candidate)
                ->whereDate('effective_from', '<=', $date)
                ->orderByDesc('effective_from')
                ->first();

            if ($rate !== null) {
                return $rate->basis_points;
            }
        }

        return 0;
    }
}
