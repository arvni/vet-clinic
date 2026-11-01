<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Models;

use App\Modules\Audit\Auditable;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A closed day: clinic-wide when user_id is null, otherwise one vet's day off.
 *
 * @property int $id
 * @property Carbon $date
 * @property int|null $user_id
 * @property string $name_fa
 * @property string|null $name_en
 */
class Holiday extends Model
{
    use Auditable, Translatable;

    protected $fillable = ['date', 'user_id', 'name_fa', 'name_en'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }
}
