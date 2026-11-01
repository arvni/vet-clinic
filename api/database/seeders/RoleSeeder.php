<?php

namespace Database\Seeders;

use App\Modules\Identity\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::values() as $role) {
            RoleModel::findOrCreate($role, 'web');
        }
    }
}
