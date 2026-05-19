<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_vendor_approvals(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.vendor-approvals.index'))
            ->assertStatus(200);
    }

    public function test_admin_can_review_vendor_application(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $applicant = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $approval = VendorApproval::create([
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.vendor-approvals.review', $approval), [
                'status' => 'approved',
                'notes' => 'Approved for marketplace access.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vendor_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $applicant->id,
            'role' => User::ROLE_VENDOR,
        ]);
    }
}
