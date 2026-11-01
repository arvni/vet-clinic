<?php

declare(strict_types=1);

namespace App\Modules\Invoices\Models;

use App\Modules\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $category
 * @property int $basis_points
 * @property Carbon $effective_from
 */
class TaxRate extends Model
{
    use Auditable;

    protected $fillable = ['category', 'basis_points', 'effective_from'];

    protected function casts(): array
    {
        return ['basis_points' => 'integer', 'effective_from' => 'date:Y-m-d'];
    }
}
