<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One weekly working period or break for a vet, in Tehran wall-clock time.
 *
 * @property int $id
 * @property int $user_id
 * @property int $weekday
 * @property string $kind
 * @property string $starts_at
 * @property string $ends_at
 */
class ScheduleBlock extends Model
{
    use Auditable;

    public const WORK = 'work';

    public const BREAK = 'break';

    protected $fillable = ['user_id', 'weekday', 'kind', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }
}
