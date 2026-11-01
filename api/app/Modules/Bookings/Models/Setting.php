<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property array<string, mixed> $value
 */
class Setting extends Model
{
    use Auditable;

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
