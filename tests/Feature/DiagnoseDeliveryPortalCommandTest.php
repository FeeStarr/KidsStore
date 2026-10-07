<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DiagnoseDeliveryPortalCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_for_linked_active_agent(): void
    {
        $user = User::create([
            'name'      => 'Linked Agent',
            'email'     => 'linked@example.com',
            'password'  => 'secret-password',
            'role'      => User::ROLE_DELIVERY_AGENT,
            'is_active' => true,
        ]);

        DeliveryAgent::create([
            'name'           => 'Linked Agent',
            'email'          => 'linked@example.com',
            'phone'          => '0800000010',
            'account_number' => 'DA-000010',
            'is_active'      => true,
            'user_id'        => $user->id,
        ]);

        $code = Artisan::call('delivery:diagnose', ['email' => 'linked@example.com']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Login checks PASS', Artisan::output());
    }

    public function test_fails_when_agent_is_not_linked_to_user(): void
    {
        User::create([
            'name'      => 'Orphan Agent',
            'email'     => 'orphan@example.com',
            'password'  => 'secret-password',
            'role'      => User::ROLE_DELIVERY_AGENT,
            'is_active' => true,
        ]);

        DeliveryAgent::create([
            'name'           => 'Orphan Agent',
            'email'          => 'orphan@example.com',
            'phone'          => '0800000011',
            'account_number' => 'DA-000011',
            'is_active'      => true,
        ]);

        $code = Artisan::call('delivery:diagnose', ['email' => 'orphan@example.com']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('no agent linked to this user', Artisan::output());
    }

    public function test_fails_when_agent_is_inactive(): void
    {
        $user = User::create([
            'name'      => 'Inactive Agent',
            'email'     => 'inactive@example.com',
            'password'  => 'secret-password',
            'role'      => User::ROLE_DELIVERY_AGENT,
            'is_active' => true,
        ]);

        DeliveryAgent::create([
            'name'           => 'Inactive Agent',
            'email'          => 'inactive@example.com',
            'phone'          => '0800000012',
            'account_number' => 'DA-000012',
            'is_active'      => false,
            'user_id'        => $user->id,
        ]);

        $code = Artisan::call('delivery:diagnose', ['email' => 'inactive@example.com']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('agent is_active=0', Artisan::output());
    }

    public function test_fails_for_unknown_email(): void
    {
        $code = Artisan::call('delivery:diagnose', ['email' => 'nobody@example.com']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('No user row in app DB', Artisan::output());
    }
}
