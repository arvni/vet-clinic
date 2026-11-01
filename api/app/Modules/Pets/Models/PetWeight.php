<?php

declare(strict_types=1);

namespace App\Modules\Pets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pet_id
 * @property int $weight_grams
 * @property Carbon $measured_at
 * @property string $source owner | vet
 * @property int|null $recorded_by
 */
class PetWeight extends Model
{
    protected $fillable = ['weight_grams', 'measured_at', 'source', 'recorded_by'];

    protected function casts(): array
    {
        return ['measured_at' => 'datetime'];
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** Kilograms as a fixed 3-decimal string, safe for JSON clients. */
    public function kilograms(): string
    {
        return number_format($this->weight_grams / 1000, 3, '.', '');
    }
}
