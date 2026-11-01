<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Support\Digits;
use App\Support\NationalCode;
use App\Support\PersianText;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->filled('name')) {
            $merge['name'] = PersianText::normalize((string) $this->input('name'));
        }
        if ($this->filled('national_code')) {
            $merge['national_code'] = Digits::toLatin(trim((string) $this->input('national_code')));
        }
        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'locale' => ['sometimes', Rule::in(['fa', 'en'])],
            'national_code' => ['sometimes', 'nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && ! NationalCode::isValid((string) $value)) {
                    $fail(__('auth.invalid_national_code'));
                }
            }],
        ];
    }
}
