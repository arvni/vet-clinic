<?php

declare(strict_types=1);

namespace App\Modules\Pets\Models;

use App\Modules\Pets\Species;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property Species $species
 * @property string $name_fa
 * @property string $name_en
 */
class Breed extends Model
{
    use Translatable;

    protected $fillable = ['species', 'name_fa', 'name_en'];

    protected function casts(): array
    {
        return ['species' => Species::class];
    }
}
