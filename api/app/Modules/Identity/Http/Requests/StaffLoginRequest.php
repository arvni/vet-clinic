<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

class StaffLoginRequest extends MobileRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mobile' => $this->mobileRules(),
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
