<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Resources;

use App\Modules\Pets\Models\PetAllergy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PetAllergy */
class PetAllergyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => ['value' => $this->type, 'label' => __('pets.allergy_type.'.$this->type)],
            'substance' => $this->substance,
            'note' => $this->note,
        ];
    }
}
