<?php

declare(strict_types=1);

namespace App\Modules\Emergencies\Models;

use App\Models\User;
use App\Modules\Audit\Auditable;
use App\Modules\Pets\Models\Pet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property int|null $pet_id
 * @property float|null $lat
 * @property float|null $lng
 * @property string|null $note
 * @property string $status
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolution
 * @property Carbon $created_at
 */
class EmergencyTicket extends Model
{
    use Auditable;

    public const OPEN = 'open';

    public const ACKNOWLEDGED = 'acknowledged';

    public const RESOLVED = 'resolved';

    protected $attributes = ['status' => self::OPEN];

    protected $fillable = [
        'owner_id', 'pet_id', 'lat', 'lng', 'note', 'status', 'acknowledged_by',
        'acknowledged_at', 'resolved_by', 'resolved_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
