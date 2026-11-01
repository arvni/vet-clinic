<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Invoices\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $owner_id
 * @property string $gateway
 * @property string $authority
 * @property int $amount_rials
 * @property string $status
 * @property string|null $ref_id
 * @property string|null $card_pan
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    use Auditable;

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    /** Money arrived but the invoice could not take it; staff must refund it. */
    public const NEEDS_REVIEW = 'needs_review';

    public const REFUNDED = 'refunded';

    protected $fillable = [
        'invoice_id', 'owner_id', 'gateway', 'authority', 'amount_rials',
        'status', 'ref_id', 'card_pan', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount_rials' => 'integer', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
