<?php

declare(strict_types=1);

namespace App\Modules\Pets\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pet_id
 * @property string $type drug | food | other
 * @property string $substance
 * @property string|null $note
 */
class PetAllergy extends Model
{
    use Auditable;

    public const TYPES = ['drug', 'food', 'other'];

    protected $fillable = ['type', 'substance', 'note'];

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
