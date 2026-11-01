<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property int|null $basis_points
 * @property int|null $fixed_rials
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $active
 */
class DiscountCode extends Model
{
    use Auditable;

    protected $attributes = ['used_count' => 0, 'active' => true];

    protected $fillable = ['code', 'basis_points', 'fixed_rials', 'max_uses', 'starts_at', 'ends_at', 'active'];

    protected function casts(): array
    {
        return [
            'basis_points' => 'integer',
            'fixed_rials' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function isUsable(): bool
    {
        return $this->active
            && ($this->max_uses === null || $this->used_count < $this->max_uses)
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
