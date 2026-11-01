<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $amount_rials
 * @property string $reason
 * @property string $reference
 * @property int $created_by
 */
class Refund extends Model
{
    use Auditable;

    protected $fillable = ['payment_id', 'amount_rials', 'reason', 'reference', 'created_by'];

    protected function casts(): array
    {
        return ['amount_rials' => 'integer'];
    }
}
