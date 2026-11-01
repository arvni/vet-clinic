<?php

declare(strict_types=1);

namespace App\Modules\Pets\Models;

use App\Models\User;
use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Pet identity and certificate documents (FR-1.7). The national document is
 * recorded (number + scan); only staff can mark it verified, and changing the
 * number or scan clears verification.
 *
 * @property int $id
 * @property int $pet_id
 * @property string $kind
 * @property string|null $number
 * @property Carbon|null $issued_at
 * @property string|null $file_path
 * @property string|null $file_mime
 * @property bool $verified
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property int|null $uploaded_by
 */
class PetDocument extends Model
{
    use Auditable, SoftDeletes;

    public const KIND_NATIONAL = 'national';

    public const KINDS = [self::KIND_NATIONAL, 'clinic_id', 'certificate', 'other'];

    protected $attributes = ['verified' => false];

    protected $fillable = ['kind', 'number', 'issued_at', 'file_path', 'file_mime', 'uploaded_by'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Any change to what was verified invalidates the verification.
        static::updating(function (self $document): void {
            if ($document->verified && $document->isDirty(['number', 'file_path', 'kind'])) {
                $document->verified = false;
                $document->verified_by = null;
                $document->verified_at = null;
            }
        });
    }

    /** @return BelongsTo<Pet, $this> */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function markVerified(User $staff, bool $verified): void
    {
        $this->verified = $verified;
        $this->verified_by = $verified ? $staff->id : null;
        $this->verified_at = $verified ? now() : null;
        $this->save();
    }
}
