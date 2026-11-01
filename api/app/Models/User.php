<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Audit\Auditable;
use App\Modules\Identity\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $mobile E.164
 * @property string|null $name
 * @property string|null $email
 * @property string|null $national_code
 * @property string|null $password
 * @property string $locale
 * @property bool $is_staff
 * @property string $status
 * @property Carbon|null $mobile_verified_at
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = [
        'mobile',
        'name',
        'email',
        'national_code',
        'password',
        'locale',
        'is_staff',
        'status',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'national_code',
    ];

    /** @return list<string> */
    protected function auditExcluded(): array
    {
        return ['last_login_at', 'national_code'];
    }

    protected function casts(): array
    {
        return [
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isStaffMember(): bool
    {
        return $this->is_staff && $this->hasAnyRole(Role::staffRoles());
    }
}
