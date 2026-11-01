<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Resources;

use App\Modules\Pets\Models\PetTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PetTransfer */
class PetTransferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pet' => [
                'id' => $this->pet_id,
                'name' => $this->pet->name,
                'species' => $this->pet->species->label(),
            ],
            'from' => $this->fromUser->name,
            'to_mobile' => $this->to_mobile,
            'status' => $this->status,
            'expires_at' => $this->expires_at->toIso8601String(),
        ];
    }
}
