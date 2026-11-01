<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kind
 * @property string|null $number
 * @property int|null $credit_of_id
 * @property int $owner_id
 * @property int|null $booking_id
 * @property string $status
 * @property int $subtotal_rials
 * @property int $vat_rials
 * @property int $total_rials
 * @property int $paid_rials
 * @property int $credited_rials
 * @property Carbon|null $issued_at
 */
class Invoice extends Model
{
    use Auditable;

    public const KIND_INVOICE = 'invoice';

    public const KIND_CREDIT_NOTE = 'credit_note';

    public const DRAFT = 'draft';

    public const ISSUED = 'issued';

    public const PARTIALLY_PAID = 'partially_paid';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    protected $attributes = [
        'kind' => self::KIND_INVOICE,
        'status' => self::DRAFT,
        'subtotal_rials' => 0,
        'vat_rials' => 0,
        'total_rials' => 0,
        'paid_rials' => 0,
        'credited_rials' => 0,
    ];

    protected $fillable = [
        'kind', 'number', 'credit_of_id', 'owner_id', 'booking_id', 'status', 'subtotal_rials',
        'vat_rials', 'total_rials', 'paid_rials', 'credited_rials', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_rials' => 'integer',
            'vat_rials' => 'integer',
            'total_rials' => 'integer',
            'paid_rials' => 'integer',
            'credited_rials' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }
}
