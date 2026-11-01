<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

class OtpVerifyRequest extends MobileRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mobile' => $this->mobileRules(),
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
