<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQcCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomOrderQcEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function orderAndCheck(): array
    {
        $order = CustomOrder::factory()->create(['status' => CustomOrder::STATUS_QUALITY_CHECK]);
        $check = CustomOrderQcCheck::create([
            'custom_order_id' => $order->id,
            'check_item'      => 'Stitching inspected',
        ]);

        return [$order, $check];
    }

    public function test_staff_can_attach_photo_evidence(): void
    {
        Storage::fake('custom_orders');
        [$order, $check] = $this->orderAndCheck();

        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.custom-orders.qc-check.evidence', [$order, $check]), [
                'photo' => UploadedFile::fake()->image('final-garment.jpg'),
            ])
            ->assertSessionHas('success');

        $check->refresh();
        $this->assertNotNull($check->photo_path);
        Storage::disk('custom_orders')->assertExists($check->photo_path);
    }

    public function test_staff_can_save_notes_only(): void
    {
        Storage::fake('custom_orders');
        [$order, $check] = $this->orderAndCheck();

        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.custom-orders.qc-check.evidence', [$order, $check]), [
                'notes' => 'Seams verified under magnification.',
            ])
            ->assertSessionHas('success');

        $check->refresh();
        $this->assertSame('Seams verified under magnification.', $check->notes);
        $this->assertNull($check->photo_path);
    }

    public function test_non_image_photo_is_rejected(): void
    {
        Storage::fake('custom_orders');
        [$order, $check] = $this->orderAndCheck();

        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.custom-orders.qc-check.evidence', [$order, $check]), [
                'photo' => UploadedFile::fake()->create('report.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($check->fresh()->photo_path);
    }

    public function test_empty_evidence_submission_is_rejected(): void
    {
        Storage::fake('custom_orders');
        [$order, $check] = $this->orderAndCheck();

        $this->actingAs($this->admin(), 'admin')
            ->patch(route('admin.custom-orders.qc-check.evidence', [$order, $check]), [
                'notes' => '',
            ])
            ->assertSessionHasErrors('notes');
    }

    public function test_qc_photo_route_is_admin_only(): void
    {
        Storage::fake('custom_orders');
        [$order, $check] = $this->orderAndCheck();

        $path = UploadedFile::fake()->image('evidence.png')
            ->storeAs("custom-orders/{$order->id}/qc", 'qc-test.png', 'custom_orders');
        $check->update(['photo_path' => $path]);

        $this->get(route('admin.custom-orders.qc-check.photo', [$order, $check]))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.qc-check.photo', [$order, $check]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        auth()->guard('admin')->logout();

        $this->actingAsCustomer();
        $this->get(route('admin.custom-orders.qc-check.photo', [$order, $check]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_show_page_shows_photo_link_after_upload(): void
    {
        Storage::fake('custom_orders');
        [$order, $check] = $this->orderAndCheck();

        $path = UploadedFile::fake()->image('evidence.png')
            ->storeAs("custom-orders/{$order->id}/qc", 'qc-test.png', 'custom_orders');
        $check->update(['photo_path' => $path]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee(route('admin.custom-orders.qc-check.photo', [$order, $check]), false);
    }
}
