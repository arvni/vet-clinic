<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

class StaffVerifyRequest extends MobileRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'challenge' => ['required', 'string', 'size:40'],
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
        ];
    }
}
