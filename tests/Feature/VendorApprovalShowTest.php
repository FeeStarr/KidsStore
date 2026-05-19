<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApprovalShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_vendor_approval_details(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $applicant = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $approval = VendorApproval::create([
            'user_id' => $applicant->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.vendor-approvals.show', $approval))
            ->assertStatus(200);
    }
}
