<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Audit\Auditable;
use App\Support\Money;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Admin-managed service (FR-2). Prices live in service_prices with an
 * effective date; priceAt() returns the price in force at a moment.
 *
 * @property int $id
 * @property string $code
 * @property string $name_fa
 * @property string|null $name_en
 * @property string|null $description_fa
 * @property string|null $description_en
 * @property string|null $icon
 * @property string $category
 * @property int $duration_minutes
 * @property list<string> $locations
 * @property list<string> $species
 * @property bool $is_addon
 * @property bool $requires_prepayment
 * @property string|null $tax_category
 * @property string|null $tax_goods_id
 * @property int $sort
 * @property bool $active
 */
class Service extends Model
{
    use Auditable, SoftDeletes, Translatable;

    public const LOCATIONS = ['clinic', 'home', 'online'];

    public const CATEGORIES = ['visit', 'vaccination', 'deworming', 'grooming', 'certificate', 'consult', 'emergency', 'other'];

    protected $attributes = [
        'species' => '[]',
        'is_addon' => false,
        'requires_prepayment' => false,
        'sort' => 100,
        'active' => true,
    ];

    protected $fillable = [
        'code', 'name_fa', 'name_en', 'description_fa', 'description_en', 'icon', 'category',
        'duration_minutes', 'locations', 'species', 'is_addon', 'requires_prepayment',
        'tax_category', 'tax_goods_id', 'sort', 'active',
    ];

    protected function casts(): array
    {
        return [
            'locations' => 'array',
            'species' => 'array',
            'is_addon' => 'boolean',
            'requires_prepayment' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<ServicePrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class)->orderByDesc('effective_from');
    }

    public function priceAt(?Carbon $at = null): ?Money
    {
        $at ??= now();

        // Use the loaded relation when available to avoid N+1 queries in listings.
        $price = $this->relationLoaded('prices')
            ? $this->prices->first(fn (ServicePrice $p) => $p->effective_from->lte($at))
            : $this->prices()->where('effective_from', '<=', $at)->first();

        return $price ? Money::rials($price->price_rials) : null;
    }

    public function availableFor(?string $species): bool
    {
        return $species === null || $this->species === [] || in_array($species, $this->species, true);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function description(?string $locale = null): ?string
    {
        return $this->translated('description', $locale);
    }
}
