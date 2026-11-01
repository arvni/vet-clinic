<?php

declare(strict_types=1);

namespace App\Modules\Pets\Http\Requests;

use App\Modules\Pets\Models\Breed;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Species;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\PersianText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * Create (POST) and edit (PATCH) a pet. Birth date may be sent as Gregorian
 * `birth_date` (Y-m-d) or Jalali `birth_date_jalali` (Y/m/d, any digits).
 */
class PetRequest extends FormRequest
{
    private bool $creating;

    protected function prepareForValidation(): void
    {
        $this->creating = $this->isMethod('POST');
        $merge = [];

        foreach (['name', 'breed_text', 'color'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = PersianText::normalize((string) $this->input($field));
            }
        }
        if ($this->filled('microchip_no')) {
            $merge['microchip_no'] = Digits::toLatin(trim((string) $this->input('microchip_no')));
        }
        if ($this->filled('weight_kg')) {
            $merge['weight_kg'] = Digits::toLatin((string) $this->input('weight_kg'));
        }
        if ($this->filled('birth_date_jalali')) {
            try {
                $merge['birth_date'] = JalaliDate::toGregorianDate((string) $this->input('birth_date_jalali'));
            } catch (Throwable) {
                $merge['birth_date'] = 'invalid';
            }
        }

        $this->merge($merge);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->creating ? 'required' : 'sometimes';
        /** @var Pet|null $pet */
        $pet = $this->route('pet');

        return [
            'name' => [$required, 'string', 'max:80'],
            'species' => [$required, Rule::in(Species::values())],
            'breed_id' => ['sometimes', 'nullable', 'integer', Rule::exists('breeds', 'id')],
            'breed_text' => ['sometimes', 'nullable', 'string', 'max:80'],
            'sex' => ['sometimes', Rule::in(Pet::SEXES)],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_date_estimated' => ['sometimes', 'boolean'],
            'neutered' => ['sometimes', 'nullable', 'boolean'],
            'color' => ['sometimes', 'nullable', 'string', 'max:80'],
            'microchip_no' => ['sometimes', 'nullable', 'string', 'regex:/^\d{9,15}$/', Rule::unique('pets', 'microchip_no')->ignore($pet?->id)],
            // First weight on create only; later weights go through the weights endpoint.
            'weight_kg' => ['sometimes', 'nullable', 'numeric', 'between:0.01,200'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $breedId = $this->input('breed_id');
            if ($breedId === null || $validator->errors()->isNotEmpty()) {
                return;
            }
            /** @var Pet|null $pet */
            $pet = $this->route('pet');
            $species = $this->input('species') ?? $pet?->species->value;
            $breed = Breed::query()->find($breedId);
            if ($breed !== null && $breed->species->value !== $species) {
                $validator->errors()->add('breed_id', __('pets.breed_species_mismatch'));
            }
        }];
    }

    /** @return array<string, mixed> */
    public function petAttributes(): array
    {
        $data = $this->safe()->except('weight_kg');
        // A listed breed replaces free text and vice versa.
        if (array_key_exists('breed_id', $data) && $data['breed_id'] !== null) {
            $data['breed_text'] = null;
        }

        return $data;
    }
}
