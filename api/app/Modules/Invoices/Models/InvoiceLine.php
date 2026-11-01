<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Models;

use App\Support\Money;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $kind
 * @property int|null $service_id
 * @property string $description_fa
 * @property string|null $description_en
 * @property int $quantity
 * @property int $unit_price_rials
 * @property int $vat_basis_points
 * @property int $discount_rials
 * @property int|null $credits_line_id
 */
class InvoiceLine extends Model
{
    use Translatable;

    public const SERVICE = 'service';

    public const HOME_VISIT_FEE = 'home_visit_fee';

    public const PRESCRIPTION = 'prescription';

    public const OTHER = 'other';

    protected $attributes = ['vat_basis_points' => 0, 'discount_rials' => 0];

    protected $fillable = [
        'kind', 'service_id', 'description_fa', 'description_en',
        'quantity', 'unit_price_rials', 'vat_basis_points', 'discount_rials', 'credits_line_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_rials' => 'integer', 'vat_basis_points' => 'integer', 'discount_rials' => 'integer'];
    }

    public function amountRials(): int
    {
        return $this->quantity * $this->unit_price_rials;
    }

    /** VAT is charged on what the owner actually pays for the line. */
    public function vatRials(): int
    {
        return Money::rials($this->amountRials() - $this->discount_rials)->percent($this->vat_basis_points)->rials;
    }
}
