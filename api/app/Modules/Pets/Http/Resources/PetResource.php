<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Resources;

use App\Modules\Files\FileStorage;
use App\Modules\Pets\Models\Pet;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pet */
class PetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'species' => ['value' => $this->species->value, 'label' => $this->species->label()],
            'breed' => [
                'id' => $this->breed_id,
                'name' => $this->breedName(),
            ],
            'sex' => ['value' => $this->sex, 'label' => __('pets.sex.'.$this->sex)],
            'birth_date' => $this->birth_date?->toDateString(),
            'birth_date_jalali' => $this->birth_date ? JalaliDate::formatDate($this->birth_date) : null,
            'birth_date_estimated' => $this->birth_date_estimated,
            'age' => $this->age(),
            'neutered' => $this->neutered,
            'color' => $this->color,
            'microchip_no' => $this->microchip_no,
            'clinic_pet_id' => $this->clinic_pet_id,
            'photo_url' => $this->photo_path ? app(FileStorage::class)->signedUrl('files.pet-photo', ['pet' => $this->id]) : null,
            'latest_weight' => $this->whenLoaded('latestWeight', fn () => $this->latestWeight ? new PetWeightResource($this->latestWeight) : null),
            'allergies' => PetAllergyResource::collection($this->whenLoaded('allergies')),
            'documents' => PetDocumentResource::collection($this->whenLoaded('documents')),
            'owner_id' => $this->owner_id,
        ];
    }
}
