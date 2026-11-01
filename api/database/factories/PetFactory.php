<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Pets\Models\Pet;
use App\Modules\Pets\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pet> */
class PetFactory extends Factory
{
    protected $model = Pet::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->owner(),
            'name' => fake()->firstName(),
            'species' => Species::Dog,
            'sex' => 'female',
            'birth_date' => now()->subMonths(30)->toDateString(),
            'birth_date_estimated' => false,
            'neutered' => true,
        ];
    }
}
