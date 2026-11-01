<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'locale' => $this->locale,
            'is_staff' => $this->is_staff,
            'roles' => $this->getRoleNames()->values(),
            // National code is shown only to its owner, masked except the last 4 digits.
            'national_code_masked' => $this->when(
                $request->user()?->id === $this->id && $this->national_code !== null,
                fn () => str_repeat('*', 6).substr((string) $this->national_code, -4),
            ),
        ];
    }
}
