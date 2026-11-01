<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Resources;

use App\Modules\Pets\Models\PetWeight;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PetWeight */
class PetWeightResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kg' => $this->kilograms(),
            'measured_at' => $this->measured_at->toIso8601String(),
            'measured_at_jalali' => JalaliDate::format($this->measured_at),
            'source' => $this->source,
        ];
    }
}
