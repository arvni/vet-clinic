<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, BreedSeeder::class, ServiceSeeder::class, TaxRateSeeder::class]);

        // First admin from env so production never ships a default password.
        $mobile = config('seed.admin_mobile');
        $password = config('seed.admin_password');
        if ($mobile && $password) {
            $admin = User::query()->firstOrCreate(
                ['mobile' => $mobile],
                ['name' => 'Admin', 'password' => $password, 'is_staff' => true, 'locale' => 'fa'],
            );
            $admin->syncRoles([Role::Admin->value]);
        }
    }
}
