<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Audit\Auditable;
use App\Support\Money;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vaccine product with its dose schedule and owner-facing price breakdown (FR-4.1).
 *
 * @property int $id
 * @property string $code
 * @property string $name_fa
 * @property string|null $name_en
 * @property string $manufacturer
 * @property list<array{fa: string, en?: string}> $diseases
 * @property list<string> $species
 * @property int $min_age_weeks
 * @property int $primary_doses
 * @property int|null $primary_interval_days
 * @property int|null $booster_interval_days
 * @property int $vaccine_cost_rials
 * @property int $service_fee_rials
 * @property int $injection_fee_rials
 * @property string|null $note_fa
 * @property string|null $note_en
 * @property bool $active
 */
class Vaccine extends Model
{
    use Auditable, SoftDeletes, Translatable;

    protected $attributes = [
        'min_age_weeks' => 0,
        'primary_doses' => 1,
        'service_fee_rials' => 0,
        'injection_fee_rials' => 0,
        'active' => true,
    ];

    protected $fillable = [
        'code', 'name_fa', 'name_en', 'manufacturer', 'diseases', 'species', 'min_age_weeks',
        'primary_doses', 'primary_interval_days', 'booster_interval_days',
        'vaccine_cost_rials', 'service_fee_rials', 'injection_fee_rials', 'note_fa', 'note_en', 'active',
    ];

    protected function casts(): array
    {
        return [
            'diseases' => 'array',
            'species' => 'array',
            'vaccine_cost_rials' => 'integer',
            'service_fee_rials' => 'integer',
            'injection_fee_rials' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function totalPrice(): Money
    {
        return Money::rials($this->vaccine_cost_rials + $this->service_fee_rials + $this->injection_fee_rials);
    }

    /** Valid for a pet of this species and age (FR-4.2). Unknown age is allowed; the vet checks in person. */
    public function suitableFor(string $species, ?int $ageWeeks): bool
    {
        return in_array($species, $this->species, true)
            && ($ageWeeks === null || $ageWeeks >= $this->min_age_weeks);
    }

    /**
     * @param  Builder<Vaccine>  $query
     * @return Builder<Vaccine>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** @param  Builder<Vaccine>  $query
     * @return Builder<Vaccine>
     */
    public function scopeForSpecies(Builder $query, string $species): Builder
    {
        return $query->whereJsonContains('species', $species);
    }

    /** @return list<string> */
    public function diseaseNames(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return array_map(fn (array $d) => ($locale === 'en' && ! empty($d['en'])) ? $d['en'] : $d['fa'], $this->diseases);
    }
}
