<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServicePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    /** @return array<int|string, mixed> mergeWhen() contributes a numeric-keyed MergeValue */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->is('api/v1/admin/*');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name(),
            'description' => $this->description(),
            'icon' => $this->icon,
            'category' => $this->category,
            'duration_minutes' => $this->duration_minutes,
            'locations' => $this->locations,
            'species' => $this->species,
            'is_addon' => $this->is_addon,
            'requires_prepayment' => $this->requires_prepayment,
            // null = price not set yet; the UI shows "price on request".
            'price' => $this->priceAt(),
            $this->mergeWhen($isAdmin, fn () => [
                'name_fa' => $this->name_fa,
                'name_en' => $this->name_en,
                'description_fa' => $this->description_fa,
                'description_en' => $this->description_en,
                'tax_category' => $this->tax_category,
                'tax_goods_id' => $this->tax_goods_id,
                'sort' => $this->sort,
                'active' => $this->active,
                'price_history' => $this->prices->map(fn (ServicePrice $p) => [
                    'price_rials' => (string) $p->price_rials,
                    'effective_from' => $p->effective_from->toIso8601String(),
                ])->values(),
            ]),
        ];
    }
}
