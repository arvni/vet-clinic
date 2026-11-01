<?php

declare(strict_types=1);

namespace App\Modules\Locations\Models;

use App\Modules\Audit\Auditable;
use App\Support\Money;
use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-drawn home-visit pricing zone. The fee applies to home visits only;
 * consult prices are the same everywhere.
 *
 * @property int $id
 * @property string $name_fa
 * @property string|null $name_en
 * @property list<array{0: float, 1: float}> $polygon
 * @property int $home_visit_fee_rials
 * @property int $sort
 * @property bool $active
 */
class ServiceZone extends Model
{
    use Auditable, Translatable;

    protected $attributes = ['sort' => 100, 'active' => true];

    protected $fillable = ['name_fa', 'name_en', 'polygon', 'home_visit_fee_rials', 'sort', 'active'];

    protected function casts(): array
    {
        return ['polygon' => 'array', 'home_visit_fee_rials' => 'integer', 'active' => 'boolean'];
    }

    public function fee(): Money
    {
        return Money::rials($this->home_visit_fee_rials);
    }

    /** Ray casting over the outer ring. */
    public function contains(float $lat, float $lng): bool
    {
        $ring = $this->polygon;
        $inside = false;

        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$latI, $lngI] = $ring[$i];
            [$latJ, $lngJ] = $ring[$j];

            if (($lngI > $lng) !== ($lngJ > $lng) && $lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    public static function findFor(float $lat, float $lng): ?self
    {
        return self::query()->where('active', true)->orderBy('sort')->orderBy('id')->get()
            ->first(fn (self $zone) => $zone->contains($lat, $lng));
    }
}
