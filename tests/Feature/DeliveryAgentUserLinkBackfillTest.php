<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAgentUserLinkBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function runBackfill(): void
    {
        $migration = include database_path('migrations/2026_10_07_000001_backfill_delivery_agent_user_links.php');
        $migration->up();
    }

    public function test_backfill_links_preexisting_agent_by_email_and_portal_login_succeeds(): void
    {
        $user = User::create([
            'name'       => 'Legacy Agent',
            'email'      => 'legacy-agent@example.com',
            'password'   => 'secret-password',
            'role'       => User::ROLE_DELIVERY_AGENT,
            'is_active'  => true,
        ]);

        $agent = DeliveryAgent::create([
            'name'           => 'Legacy Agent',
            'email'          => 'legacy-agent@example.com',
            'phone'          => '0800000000',
            'account_number' => 'DA-000001',
            'is_active'      => true,
        ]);

        $this->assertNull($agent->fresh()->user_id);

        $this->runBackfill();

        $this->assertSame($user->id, $agent->fresh()->user_id);

        $response = $this->post('/delivery-portal/login', [
            'email'    => 'legacy-agent@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('delivery-portal.dashboard'));
    }

    public function test_backfill_does_not_hijack_user_already_linked_to_another_agent(): void
    {
        $user = User::create([
            'name'      => 'Linked Agent',
            'email'     => 'linked@example.com',
            'password'  => 'secret-password',
            'role'      => User::ROLE_DELIVERY_AGENT,
            'is_active' => true,
        ]);

        $linked = DeliveryAgent::create([
            'name'           => 'Linked Agent',
            'email'          => 'linked@example.com',
            'phone'          => '0800000001',
            'account_number' => 'DA-000002',
            'is_active'      => true,
            'user_id'        => $user->id,
        ]);

        $orphan = DeliveryAgent::create([
            'name'           => 'Duplicate Email Agent',
            'email'          => 'linked@example.com',
            'phone'          => '0800000002',
            'account_number' => 'DA-000003',
            'is_active'      => true,
        ]);

        $this->runBackfill();

        $this->assertNull($orphan->fresh()->user_id);
        $this->assertSame($user->id, $linked->fresh()->user_id);
    }

    public function test_backfill_ignores_users_without_delivery_agent_role(): void
    {
        User::create([
            'name'      => 'Customer With Agent Email',
            'email'     => 'not-an-agent@example.com',
            'password'  => 'secret-password',
            'role'      => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $agent = DeliveryAgent::create([
            'name'           => 'Agent Row',
            'email'          => 'not-an-agent@example.com',
            'phone'          => '0800000003',
            'account_number' => 'DA-000004',
            'is_active'      => true,
        ]);

        $this->runBackfill();

        $this->assertNull($agent->fresh()->user_id);
    }
}
