<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable price row: prices change by adding a new row with a later
 * effective date, never by editing history.
 *
 * @property int $id
 * @property int $service_id
 * @property int $price_rials
 * @property Carbon $effective_from
 * @property int|null $created_by
 */
class ServicePrice extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['price_rials', 'effective_from', 'created_by'];

    protected function casts(): array
    {
        return [
            'price_rials' => 'integer',
            'effective_from' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Service prices are immutable; add a new price instead.'));
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
