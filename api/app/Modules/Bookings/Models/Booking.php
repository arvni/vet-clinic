<?php

declare(strict_types=1);

namespace App\Modules\Bookings\Models;

use App\Models\User;
use App\Modules\Audit\Auditable;
use App\Modules\Catalog\Models\Service;
use App\Modules\Locations\Models\Address;
use App\Modules\Pets\Models\Pet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property int $pet_id
 * @property int $service_id
 * @property int $vet_id
 * @property string $location
 * @property int|null $address_id
 * @property int|null $zone_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 * @property int|null $service_price_rials
 * @property int $home_visit_fee_rials
 * @property string|null $notes
 */
class Booking extends Model
{
    use Auditable;

    public const PENDING_PAYMENT = 'pending_payment';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    protected $attributes = ['home_visit_fee_rials' => 0];

    protected $fillable = [
        'owner_id', 'pet_id', 'service_id', 'vet_id', 'location', 'address_id', 'zone_id',
        'starts_at', 'ends_at', 'status', 'service_price_rials', 'home_visit_fee_rials', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'service_price_rials' => 'integer',
            'home_visit_fee_rials' => 'integer',
        ];
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function vet(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vet_id');
    }

    /** @return BelongsTo<Address, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class)->withTrashed();
    }
}
