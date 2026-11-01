<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Vaccine;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vaccine */
class VaccineResource extends JsonResource
{
    /** @return array<int|string, mixed> mergeWhen() contributes a numeric-keyed MergeValue */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name(),
            'manufacturer' => $this->manufacturer,
            'diseases' => $this->diseaseNames(),
            'species' => $this->species,
            'min_age_weeks' => $this->min_age_weeks,
            'schedule' => [
                'primary_doses' => $this->primary_doses,
                'primary_interval_days' => $this->primary_interval_days,
                'booster_interval_days' => $this->booster_interval_days,
            ],
            // FR-4.1: owners see the breakdown before paying.
            'price' => [
                'vaccine' => Money::rials($this->vaccine_cost_rials),
                'service_fee' => Money::rials($this->service_fee_rials),
                'injection_fee' => Money::rials($this->injection_fee_rials),
                'total' => $this->totalPrice(),
            ],
            'note' => $this->translated('note'),
            $this->mergeWhen($isAdmin, fn () => [
                'name_fa' => $this->name_fa,
                'name_en' => $this->name_en,
                'diseases_raw' => $this->diseases,
                'note_fa' => $this->note_fa,
                'note_en' => $this->note_en,
                'active' => $this->active,
            ]),
        ];
    }
}
