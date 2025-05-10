<?php
namespace Database\Seeders;

// to run this file type this in terminal
// php artisan db:seed --class=UsersTableSeeder

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [
            [
                'name'                => 'John Doe',
                'email'               => 'johndoe@gmail.com',
                'status'              => 'active',
                'password'            => Hash::make('#@1Password'),
                'role'                => 'System Admin',
                'lastlogin'           => now(),
                'photo_url'           => null,
                'agree'               => true,
                'phone'               => '256774542872',
                'age'                 => 35,
                'date_of_birth'       => '1990-01-01',
                'gender'              => 'Male',
                'nationality'         => 'Ugandan',
                'allow_notifications' => true,
            ],
            [
                'name'                => 'Jane Doe',
                'email'               => 'janedoe@gmail.com',
                'status'              => 'active',
                'password'            => Hash::make('#@1Password'),
                'role'                => 'Client',
                'lastlogin'           => now(),
                'photo_url'           => null,
                'agree'               => true,
                'phone'               => '256704542872',
                'age'                 => 30,
                'date_of_birth'       => '1994-02-14',
                'gender'              => 'Female',
                'nationality'         => 'Ugandan',
                'allow_notifications' => true,
            ],
        ];

        $existingUsers = [];
        $createdUsers  = [];

        // Begin transaction
        DB::beginTransaction();

        try {
            foreach ($users as $userData) {
                // Remove 'role' from the array before creating the user
                $roleName = $userData['role'];
                unset($userData['role']);

                $user = User::firstOrCreate(
                    ['email' => $userData['email']],
                    $userData
                );

                if ($user->wasRecentlyCreated) {
                    $createdUsers[] = $user->email;
                } else {
                    $existingUsers[] = $user->email;
                }

                // Check if the role exists and assign it to the user
                if (Role::where('name', $roleName)->exists()) {
                    // Remove existing roles
                    $user->roles()->detach();
                    $user->assignRole($roleName);
                }
            }

            // If we get here, it means no exceptions were thrown
            // So we commit the changes
            DB::commit();

            // Output to the console
            $this->command->info('Existing Users: ' . implode(', ', $existingUsers));
            $this->command->info('Created Users: ' . implode(', ', $createdUsers));

        } catch (\Exception $e) {
            // An exception was thrown
            // We roll back the changes
            DB::rollback();

            $this->command->error('Error seeding users: ' . $e->getMessage());
        }
    }
}
