<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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