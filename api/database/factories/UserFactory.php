<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mobile' => '+989'.fake()->unique()->numerify('#########'),
            'name' => fake()->name(),
            'locale' => 'fa',
            'is_staff' => false,
            'status' => User::STATUS_ACTIVE,
            'mobile_verified_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Owner->value));
    }

    public function staff(Role ...$roles): static
    {
        return $this->state(['is_staff' => true, 'password' => 'Secret12345'])
            ->afterCreating(fn (User $user) => $user->syncRoles(array_map(fn (Role $r) => $r->value, $roles)));
    }

    public function suspended(): static
    {
        return $this->state(['status' => User::STATUS_SUSPENDED]);
    }
}
