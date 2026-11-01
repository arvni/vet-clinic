<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Role;
use App\Support\PersianText;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends MobileRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if ($this->filled('name')) {
            $this->merge(['name' => PersianText::normalize((string) $this->input('name'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mobile' => [...$this->mobileRules(), Rule::unique('users', 'mobile')],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', Password::min(10)->letters()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(Role::staffRoles())],
            'locale' => ['sometimes', Rule::in(['fa', 'en'])],
        ];
    }
}
