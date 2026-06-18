<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * WARNING: This seeder creates default admin accounts with weak passwords.
     * It should ONLY be used in development. Do not run this in production.
     * Create strong, unique admin accounts manually in production environments.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('Skipping AdminUserSeeder - default accounts are not allowed in production.');
            return;
        }

        User::firstOrCreate(
            ['email' => 'admin@kidsstore.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_SUPERADMIN,
            ]
        );

        User::firstOrCreate(
            ['email' => 'support@kidsstore.com'],
            [
                'name' => 'Support User',
                'password' => Hash::make('password'),
                'role' => User::ROLE_STAFF,
            ]
        );

        User::firstOrCreate(
            ['email' => 'nafiyoza@gmail.com'],
            [
                'name' => 'Nafiyoza',
                'password' => Hash::make('Admin123'),
                'role' => User::ROLE_SUPERADMIN,
            ]
        );
    }
}