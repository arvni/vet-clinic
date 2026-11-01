<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Resources;

use App\Modules\Files\FileStorage;
use App\Modules\Pets\Models\PetDocument;
use App\Support\JalaliDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PetDocument */
class PetDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => ['value' => $this->kind, 'label' => __('pets.document_kind.'.$this->kind)],
            'number' => $this->number,
            'issued_at' => $this->issued_at?->toDateString(),
            'verified' => $this->verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_at_jalali' => $this->verified_at ? JalaliDate::format($this->verified_at) : null,
            'file_url' => $this->file_path ? app(FileStorage::class)->signedUrl('files.pet-document', ['document' => $this->id]) : null,
            'file_mime' => $this->file_mime,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
