<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// php artisan db:seed --class=RolesAndPermissionsSeeder

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        $rolesWithPermissions = [

            'System Admin' => [
                'view user',
                'create user',
                'edit user',
                'delete user',
            ],

            'Client'       => [
                'view user',
            ],

        ];

        $this->createRolesAndPermissions($rolesWithPermissions);

        $this->command->info("Roles & Permissions Seeded Succesfully.");
    }

    private function createRolesAndPermissions(array $rolesWithPermissions)
    {
        foreach ($rolesWithPermissions as $roleName => $permissions) {
            // Create or retrieve the role
            $role = Role::firstOrCreate(['name' => $roleName]);

            // Ensure all permissions exist
            $permissionInstances = collect($permissions)->map(function ($permissionName) {
                return Permission::firstOrCreate(['name' => $permissionName]);
            });

            // Sync permissions with the role
            $role->syncPermissions($permissionInstances);
        }
    }
}
