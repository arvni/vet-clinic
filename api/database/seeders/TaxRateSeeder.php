<?php

namespace Database\Seeders;

use App\Modules\Invoices\Models\TaxRate;
use App\Modules\Invoices\TaxRates;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    /** General rate for 1405 (FR-5.3); exemptions are added by admins once the accountant confirms them. */
    public function run(): void
    {
        TaxRate::query()->firstOrCreate(
            ['category' => TaxRates::GENERAL, 'effective_from' => '2026-03-21'],
            ['basis_points' => 1000],
        );
    }
}
