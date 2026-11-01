<?php

declare(strict_types=1);

namespace App\Modules\Pets\Models;

use App\Models\User;
use App\Modules\Audit\Auditable;
use App\Modules\Pets\Species;
use Database\Factories\PetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property Species $species
 * @property int|null $breed_id
 * @property string|null $breed_text
 * @property string $sex
 * @property Carbon|null $birth_date
 * @property bool $birth_date_estimated
 * @property bool|null $neutered
 * @property string|null $color
 * @property string|null $microchip_no
 * @property string|null $clinic_pet_id
 * @property string|null $photo_path
 */
class Pet extends Model
{
    use Auditable;

    /** @use HasFactory<PetFactory> */
    use HasFactory, SoftDeletes;

    public const SEXES = ['male', 'female', 'unknown'];

    /** Mirror DB defaults so new instances serialize correctly before a refresh. */
    protected $attributes = [
        'sex' => 'unknown',
        'birth_date_estimated' => false,
    ];

    protected $fillable = [
        'name', 'species', 'breed_id', 'breed_text', 'sex', 'birth_date',
        'birth_date_estimated', 'neutered', 'color', 'microchip_no',
    ];

    protected function casts(): array
    {
        return [
            'species' => Species::class,
            'birth_date' => 'date',
            'birth_date_estimated' => 'boolean',
            'neutered' => 'boolean',
        ];
    }

    protected static function newFactory(): PetFactory
    {
        return PetFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Breed, $this> */
    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    /** @return HasMany<PetWeight, $this> */
    public function weights(): HasMany
    {
        return $this->hasMany(PetWeight::class)->orderByDesc('measured_at')->orderByDesc('id');
    }

    /** @return HasOne<PetWeight, $this> */
    public function latestWeight(): HasOne
    {
        return $this->hasOne(PetWeight::class)->latestOfMany('measured_at');
    }

    /** @return HasMany<PetAllergy, $this> */
    public function allergies(): HasMany
    {
        return $this->hasMany(PetAllergy::class);
    }

    /** @return HasMany<PetDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PetDocument::class);
    }

    /**
     * Age in whole years and months, or null when the birth date is unknown.
     *
     * @return array{years: int, months: int, estimated: bool}|null
     */
    public function age(?Carbon $at = null): ?array
    {
        if ($this->birth_date === null) {
            return null;
        }

        $at ??= now();
        $months = max(0, (int) $this->birth_date->diffInMonths($at, false));

        return [
            'years' => intdiv($months, 12),
            'months' => $months % 12,
            'estimated' => $this->birth_date_estimated,
        ];
    }

    public function ageInWeeks(?Carbon $at = null): ?int
    {
        return $this->birth_date === null ? null : max(0, (int) $this->birth_date->diffInWeeks($at ?? now(), false));
    }

    public function breedName(?string $locale = null): ?string
    {
        return $this->breed?->name($locale) ?? $this->breed_text;
    }
}
