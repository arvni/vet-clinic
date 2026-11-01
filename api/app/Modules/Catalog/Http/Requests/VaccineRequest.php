<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Vaccine;
use App\Modules\Pets\Species;
use App\Support\Digits;
use App\Support\PersianText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Prices arrive in toman (what admins think in) and are stored in rials. */
class VaccineRequest extends FormRequest
{
    private const PRICE_FIELDS = ['vaccine_cost_toman', 'service_fee_toman', 'injection_fee_toman'];

    /** Authorize before validating so unauthorized callers learn nothing from validation errors. */
    public function authorize(): bool
    {
        return Gate::allows('manage-catalog');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        foreach (self::PRICE_FIELDS as $field) {
            if ($this->filled($field)) {
                $merge[$field] = Digits::toLatin(str_replace([',', '٬', ' '], '', (string) $this->input($field)));
            }
        }
        foreach (['name_fa', 'note_fa'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = PersianText::normalize((string) $this->input($field));
            }
        }
        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        /** @var Vaccine|null $vaccine */
        $vaccine = $this->route('vaccine');

        return [
            'code' => [$required, 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('vaccines', 'code')->ignore($vaccine?->id)],
            'name_fa' => [$required, 'string', 'max:120'],
            'name_en' => ['sometimes', 'nullable', 'string', 'max:120'],
            'manufacturer' => [$required, 'string', 'max:120'],
            'diseases' => [$required, 'array', 'min:1'],
            'diseases.*.fa' => ['required', 'string', 'max:120'],
            'diseases.*.en' => ['nullable', 'string', 'max:120'],
            'species' => [$required, 'array', 'min:1'],
            'species.*' => [Rule::in(Species::values())],
            'min_age_weeks' => ['sometimes', 'integer', 'between:0,520'],
            'primary_doses' => ['sometimes', 'integer', 'between:1,6'],
            'primary_interval_days' => ['nullable', 'integer', 'between:1,365', 'required_if:primary_doses,2,3,4,5,6'],
            'booster_interval_days' => ['sometimes', 'nullable', 'integer', 'between:1,1825'],
            'vaccine_cost_toman' => [$required, 'integer', 'min:0'],
            'service_fee_toman' => ['sometimes', 'integer', 'min:0'],
            'injection_fee_toman' => ['sometimes', 'integer', 'min:0'],
            'note_fa' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'note_en' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function vaccineAttributes(): array
    {
        $data = $this->safe()->except(self::PRICE_FIELDS);
        foreach (self::PRICE_FIELDS as $field) {
            if ($this->safe()->has($field)) {
                $data[str_replace('_toman', '_rials', $field)] = (int) $this->validated($field) * 10;
            }
        }

        return $data;
    }
}
