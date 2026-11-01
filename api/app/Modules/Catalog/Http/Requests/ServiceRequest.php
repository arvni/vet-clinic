<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Service;
use App\Modules\Pets\Species;
use App\Support\Digits;
use App\Support\PersianText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /** Authorize before validating so unauthorized callers learn nothing from validation errors. */
    public function authorize(): bool
    {
        return Gate::allows('manage-catalog');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (['name_fa', 'description_fa'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = PersianText::normalize((string) $this->input($field));
            }
        }
        if ($this->filled('price_toman')) {
            $merge['price_toman'] = Digits::toLatin(str_replace([',', '٬', ' '], '', (string) $this->input('price_toman')));
        }
        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';
        /** @var Service|null $service */
        $service = $this->route('service');

        return [
            'code' => [$required, 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('services', 'code')->ignore($service?->id)],
            'name_fa' => [$required, 'string', 'max:120'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:120'],
            'description_fa' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description_en' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:40'],
            'category' => [$required, Rule::in(Service::CATEGORIES)],
            'duration_minutes' => [$required, 'integer', 'between:5,480'],
            'locations' => [$required, 'array', 'min:1'],
            'locations.*' => [Rule::in(Service::LOCATIONS)],
            'species' => ['sometimes', 'array'],
            'species.*' => [Rule::in(Species::values())],
            'is_addon' => ['sometimes', 'boolean'],
            'requires_prepayment' => ['sometimes', 'boolean'],
            'tax_category' => ['sometimes', 'nullable', 'string', 'max:32'],
            'tax_goods_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort' => ['sometimes', 'integer', 'between:0,1000'],
            'active' => ['sometimes', 'boolean'],
            // Initial price on create only; later changes go through the prices endpoint.
            'price_toman' => [$creating ? 'sometimes' : 'prohibited', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, mixed> */
    public function serviceAttributes(): array
    {
        return $this->safe()->except('price_toman');
    }
}
