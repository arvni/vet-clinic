<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An owner's saved location for home visits.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $line
 * @property string|null $details
 * @property float $lat
 * @property float $lng
 */
class Address extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['user_id', 'label', 'line', 'details', 'lat', 'lng'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }
}
