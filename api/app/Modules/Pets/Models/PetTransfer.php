<?php

declare(strict_types=1);

namespace App\Modules\Pets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pet_id
 * @property int $from_user_id
 * @property string $to_mobile
 * @property int|null $to_user_id
 * @property string $status pending | accepted | cancelled
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 */
class PetTransfer extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const CANCELLED = 'cancelled';

    public const TTL_DAYS = 7;

    protected $attributes = ['status' => self::PENDING];

    protected $fillable = ['to_mobile', 'from_user_id', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @param  Builder<PetTransfer>  $query
     * @return Builder<PetTransfer>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::PENDING)->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->status === self::PENDING && $this->expires_at->isFuture();
    }
}
