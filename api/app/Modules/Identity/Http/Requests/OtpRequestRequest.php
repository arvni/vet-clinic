<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

class OtpRequestRequest extends MobileRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['mobile' => $this->mobileRules()];
    }
}
