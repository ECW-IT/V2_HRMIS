<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_INITIAL_PASSWORD');

        if ($password === null || $password === '') {
            throw new \RuntimeException('ADMIN_INITIAL_PASSWORD is not set. Set it in your environment before seeding the admin user.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@ecw.local'],
            [
                'name' => 'System Admin',
                'password' => $password,
                'is_active' => true,
                'all_companies' => true,
                'must_change_password' => true,
            ],
        );

        $user->syncRoles(['super-admin']);
    }
}
