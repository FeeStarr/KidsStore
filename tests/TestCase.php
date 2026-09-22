<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Models\User;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function makeCustomer(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'     => 'Test Customer',
            'email'    => fake()->unique()->safeEmail(),
            'password' => 'password123',
            'role'     => User::ROLE_CUSTOMER,
            'is_active' => true,
        ], $overrides));
    }

    protected function actingAsCustomer(User $user = null): User
    {
        $user ??= $this->makeCustomer();
        $this->actingAs($user, 'web');
        return $user;
    }
}
