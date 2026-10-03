<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\RefundStatusNotification;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RefundStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function refundRequest(string $status): RefundRequest
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = Order::create([
            'reference'        => 'ORD-RFNOTIF-' . uniqid(),
            'lookup_token'     => 'rf-notif-' . uniqid(),
            'order_date'       => now()->toDateString(),
            'customer_id'      => $customer->id,
            'status'           => 'delivered',
            'delivery_method'  => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount'     => 1000,
            'grand_total'      => 1000,
            'amount_paid'      => 1000,
            'payment_status'   => 'paid',
        ]);
        $product = Product::create([
            'sku'           => 'RFNOTIF1',
            'name'          => 'Refund Notify Product',
            'slug'          => 'refund-notify-product',
            'selling_price' => 100,
        ]);
        $item = OrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'quantity'            => 1,
            'unit_price'          => 100,
            'original_unit_price' => 100,
            'discount'            => 0,
            'line_total'          => 100,
        ]);

        return RefundRequest::create([
            'order_id'      => $order->id,
            'order_item_id' => $item->id,
            'status'        => $status,
            'reason'        => 'damaged',
            'details'       => 'Item was damaged.',
            'quantity'      => 1,
            'amount'        => 100,
        ]);
    }

    public function test_approved_and_processing_emails_are_distinct(): void
    {
        $approved = $this->refundRequest(RefundRequest::STATUS_REFUND_APPROVED);
        $approvedSubject = (new RefundStatusNotification($approved))
            ->toMail($approved->order->customer)
            ->subject;

        $approved->update(['status' => RefundRequest::STATUS_REFUND_PROCESSING]);
        $processing = $approved->fresh();
        $processingSubject = (new RefundStatusNotification($processing))
            ->toMail($processing->order->customer)
            ->subject;

        $this->assertSame("Refund approved - {$processing->order->reference}", $approvedSubject);
        $this->assertSame("Refund processing - {$processing->order->reference}", $processingSubject);
        $this->assertNotSame($approvedSubject, $processingSubject);
    }

    public function test_apply_refund_success_notifies_the_customer_only_once(): void
    {
        Notification::fake();
        $refund = $this->refundRequest(RefundRequest::STATUS_REFUND_APPROVED);
        $customer = $refund->order->customer;
        $service = app(RefundService::class);

        $service->applyRefundSuccess($refund);
        $service->applyRefundSuccess($refund->fresh());

        $this->assertSame(RefundRequest::STATUS_REFUNDED, $refund->fresh()->status);
        Notification::assertSentToTimes($customer, RefundStatusNotification::class, 1);
    }
}
