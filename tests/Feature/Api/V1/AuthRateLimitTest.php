<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_register_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(429)
            ->assertJson(['error' => 'Too many requests. Please slow down.']);
    }

    public function test_api_login_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'nobody@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
