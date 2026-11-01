<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Support\Digits;
use App\Support\IranMobile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request that normalizes `mobile` to E.164 and digit fields to ASCII
 * before validation, so rules always see canonical values.
 */
abstract class MobileRequest extends FormRequest
{
    /** @var list<string> */
    protected array $digitFields = ['code'];

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('mobile')) {
            $merge['mobile'] = IranMobile::normalize((string) $this->input('mobile')) ?? $this->input('mobile');
        }

        foreach ($this->digitFields as $field) {
            if ($this->filled($field)) {
                $merge[$field] = Digits::toLatin(trim((string) $this->input($field)));
            }
        }

        $this->merge($merge);
    }

    public function mobile(): string
    {
        return (string) $this->validated('mobile');
    }

    /** @return list<string> */
    protected function mobileRules(): array
    {
        return ['required', 'string', 'regex:/^\+989\d{9}$/'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['mobile.regex' => __('auth.invalid_mobile')];
    }
}
