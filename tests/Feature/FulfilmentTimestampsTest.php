<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupStation;
use App\Services\DeliveryAgentService;
use App\Services\OrderService;
use App\Services\PickupStationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Fulfilment timestamps.
 *
 * The reporting window for a custom frock starts when it is actually delivered or
 * collected. Two paths marked an order delivered without recording that moment:
 * PickupStationService set status 'delivered' on final collection with no timestamp
 * at all, and Order::markAsDelivered() set status without delivered_at.
 *
 * These cover the real entry points, not just the underlying methods, because the
 * pickup-portal and delivery-agent routes bypass markPickedUp entirely.
 */
class FulfilmentTimestampsTest extends TestCase
{
    private function station(): PickupStation
    {
        return PickupStation::create([
            'name' => 'Test Station',
            'address' => '1 Test Road',
            'email' => 'station@test.ng',
            'is_active' => true,
        ]);
    }

    private function deliveryOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'reference' => 'ORD-FT-'.uniqid(),
            'order_date' => now(),
            'status' => 'out for delivery',
            'delivery_method' => 'delivery',
            'delivery_status' => 'out_for_delivery',
            'payment_status' => 'paid',
            'subtotal' => 15000,
            'grand_total' => 15000,
            'total_amount' => 15000,
            'amount_paid' => 15000,
        ], $overrides));
    }

    private function pickupOrder(array $overrides = []): Order
    {
        return $this->deliveryOrder(array_merge([
            'delivery_method' => 'pickup',
            'delivery_status' => 'pending',
            'status' => 'ready for pick up',
        ], $overrides));
    }

    private function item(Order $order, string $pickupStatus): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => null,
            'quantity' => 1,
            'unit_price' => 15000,
            'line_total' => 15000,
            'pickup_status' => $pickupStatus,
        ]);
    }

    private function agent(Order $order): DeliveryAgent
    {
        $agent = DeliveryAgent::create([
            'account_number' => 'ACC'.uniqid(),
            'name' => 'Test Agent',
            'is_active' => true,
        ]);

        $order->update(['delivery_agent_id' => $agent->id]);

        return $agent;
    }

    // ── classification ────────────────────────────────────────────────────

    public function test_delivery_method_is_the_authoritative_classifier(): void
    {
        $station = $this->station();

        // A station id set on a delivery order must not make it a pickup order.
        $delivery = $this->deliveryOrder(['pickup_station_id' => $station->id]);

        $this->assertTrue($delivery->isDeliveryOrder());
        $this->assertFalse($delivery->isPickupOrder());

        $pickup = $this->pickupOrder();
        $this->assertTrue($pickup->isPickupOrder());
        $this->assertFalse($pickup->isDeliveryOrder());
    }

    public function test_fulfilment_completed_at_returns_null_rather_than_guessing(): void
    {
        $order = $this->deliveryOrder(['delivered_at' => null, 'delivery_delivered_at' => null]);
        $this->assertNull(
            $order->fulfilmentCompletedAt(),
            'A missing fulfilment timestamp must stay null so callers verify manually.'
        );

        $known = now()->subDays(2)->startOfSecond();
        $stamped = $this->deliveryOrder(['delivered_at' => $known]);
        $this->assertTrue($known->equalTo($stamped->fulfilmentCompletedAt()));

        // Pickup reads picked_up_at only.
        $collected = $this->pickupOrder(['picked_up_at' => $known, 'delivered_at' => null]);
        $this->assertTrue($known->equalTo($collected->fulfilmentCompletedAt()));
    }

    // ── station collection ────────────────────────────────────────────────

    public function test_collecting_the_final_item_stamps_picked_up_at(): void
    {
        Notification::fake();
        $station = $this->station();
        $order = $this->pickupOrder(['pickup_station_id' => $station->id]);
        $item = $this->item($order, 'ready for pickup');

        app(PickupStationService::class)->markPickedUp($item, $station->id);

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_DELIVERED, $fresh->status);
        $this->assertNotNull($fresh->picked_up_at, 'Collection must be timestamped.');
        $this->assertNull(
            $fresh->delivered_at,
            'A pickup order must not get a courier delivery timestamp.'
        );
    }

    public function test_repeated_collection_never_resets_the_original_time(): void
    {
        Notification::fake();
        $station = $this->station();
        $order = $this->pickupOrder(['pickup_station_id' => $station->id]);
        $item = $this->item($order, 'ready for pickup');

        $service = app(PickupStationService::class);
        $service->markPickedUp($item, $station->id);
        $first = $order->fresh()->picked_up_at;

        // A retry of the admin action must not move the clock.
        app(OrderService::class)->markDelivered($order->fresh());

        $this->assertTrue(
            $first->equalTo($order->fresh()->picked_up_at),
            'A retry must not reset the original collection time.'
        );
    }

    public function test_readiness_does_not_stamp_a_collection_time(): void
    {
        $station = $this->station();
        $order = $this->pickupOrder(['pickup_station_id' => $station->id, 'status' => 'out for delivery']);
        $item = $this->item($order, 'received');

        app(PickupStationService::class)->markReady($item, $station->id);

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_READY_FOR_PICKUP, $fresh->status);
        $this->assertNull(
            $fresh->picked_up_at,
            'Being ready for collection is not collection; no timestamp may be stamped.'
        );
    }

    // ── markDelivered / pickup portal ─────────────────────────────────────

    public function test_pickup_portal_mark_delivered_stamps_collection_not_delivery(): void
    {
        Notification::fake();
        // No station and no station service involved: this is the PickupPortalController
        // route that calls OrderService::markDelivered() directly.
        $order = $this->pickupOrder(['status' => 'ready for pick up']);

        app(OrderService::class)->markDelivered($order);

        $fresh = $order->fresh();
        $this->assertSame(Order::STATUS_DELIVERED, $fresh->status);
        $this->assertNotNull($fresh->picked_up_at);
        $this->assertNull(
            $fresh->delivered_at,
            'The pickup branch must not create a courier delivery timestamp.'
        );
    }

    public function test_delivery_mark_delivered_stamps_delivered_at(): void
    {
        Notification::fake();
        $order = $this->deliveryOrder(['status' => 'processing']);

        app(OrderService::class)->markDelivered($order);

        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
        $this->assertNull($order->fresh()->picked_up_at);
    }

    public function test_mark_delivered_is_idempotent(): void
    {
        Notification::fake();
        $order = $this->deliveryOrder(['status' => 'processing']);

        app(OrderService::class)->markDelivered($order);
        $first = $order->fresh()->delivered_at;

        app(OrderService::class)->markDelivered($order->fresh());

        $this->assertTrue(
            $first->equalTo($order->fresh()->delivered_at),
            'Repeated completion must not reset the original delivery time.'
        );
    }

    // ── delivery agent ────────────────────────────────────────────────────

    public function test_delivery_agent_stamps_canonical_timestamp_outside_the_old_status_gate(): void
    {
        Notification::fake();
        // 'confirmed' was never in the old ['out for delivery','processing'] allowlist,
        // so this order previously got delivery_status=delivered with no delivered_at.
        // delivery_status stays 'out_for_delivery' to satisfy the agent guard.
        $order = $this->deliveryOrder([
            'status' => 'confirmed',
            'delivery_status' => 'out_for_delivery',
        ]);
        $agent = $this->agent($order);
        $o = $order->fresh();
        app(DeliveryAgentService::class)->markDelivered($o, $agent);

        $fresh = $order->fresh();
        $this->assertSame(Order::DELIVERY_STATUS_DELIVERED, $fresh->delivery_status);
        $this->assertSame(Order::STATUS_DELIVERED, $fresh->status);
        $this->assertNotNull($fresh->delivery_delivered_at);
        $this->assertNotNull(
            $fresh->delivered_at,
            'The agent path must stamp the canonical delivery timestamp.'
        );
    }

    public function test_delivery_agent_refuses_terminal_states_without_writing_anything(): void
    {
        Notification::fake();
        $order = $this->deliveryOrder([
            'status' => 'cancelled',
            'delivery_status' => 'out_for_delivery',
        ]);
        $agent = $this->agent($order);

        try {
            app(DeliveryAgentService::class)->markDelivered($order->fresh(), $agent);
            $this->fail('Expected the terminal-state guard to abort.');
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getStatusCode());
        }

        $fresh = $order->fresh();
        // Every relevant field must be untouched, not just the status.
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame(Order::DELIVERY_STATUS_OUT_FOR_DELIVERY, $fresh->delivery_status);
        $this->assertNull($fresh->delivery_delivered_at);
        $this->assertNull($fresh->delivered_at);
        $this->assertNull($fresh->picked_up_at);
    }

    public function test_delivery_agent_does_not_reset_an_existing_delivery_timestamp(): void
    {
        Notification::fake();
        $original = now()->subDays(3)->startOfSecond();
        $order = $this->deliveryOrder([
            'status' => 'out for delivery',
            'delivery_status' => 'out_for_delivery',
            'delivered_at' => $original,
        ]);
        $agent = $this->agent($order);

        app(DeliveryAgentService::class)->markDelivered($order->fresh(), $agent);

        $this->assertTrue(
            $original->equalTo($order->fresh()->delivered_at),
            'An already-delivered order must keep its original timestamp.'
        );
    }

    // ── migration ─────────────────────────────────────────────────────────

    /**
     * Creates a legacy row as it would have looked *before* the fix: already delivered but
     * with no canonical timestamp.
     *
     * Uses a raw insert on purpose. Eloquent would fire the model saving hook, which now
     * stamps delivered_at on any transition to 'delivered' - correct behaviour, but it
     * makes the pre-fix state impossible to represent through the model.
     */
    private function legacyDeliveredOrder(array $overrides = []): Order
    {
        $order = $this->deliveryOrder($overrides);

        DB::table('orders')->where('id', $order->id)->update([
            'status' => 'delivered',
            'delivered_at' => null,
            'updated_at' => now()->subDays(9),
        ]);

        return $order->fresh();
    }

    public function test_backfill_patches_delivery_orders_with_a_real_source(): void
    {
        $known = now()->subDays(5)->startOfSecond();
        $order = $this->legacyDeliveredOrder(['delivery_delivered_at' => $known]);

        $this->assertNull($order->delivered_at, 'Precondition: legacy row starts unstamped.');

        $this->runBackfill();

        $this->assertTrue($known->equalTo($order->fresh()->delivered_at));
    }

    public function test_backfill_never_assigns_a_delivery_timestamp_to_a_pickup_order(): void
    {
        // Even with a station id present, delivery_method alone decides.
        $order = $this->legacyDeliveredOrder([
            'delivery_method' => 'pickup',
            'pickup_station_id' => $this->station()->id,
            'delivery_delivered_at' => now()->subDays(2)->startOfSecond(),
        ]);

        $this->runBackfill();

        $this->assertNull(
            $order->fresh()->delivered_at,
            'A pickup order must never receive a courier delivery timestamp from the backfill.'
        );
    }

    public function test_backfill_never_infers_a_date_from_updated_at(): void
    {
        $order = $this->legacyDeliveredOrder(['delivery_delivered_at' => null]);

        $this->runBackfill();

        $this->assertNull(
            $order->fresh()->delivered_at,
            'updated_at is meaningless as a fulfilment date and must never be used.'
        );
    }

    /**
     * Invokes the real migration's up() so the test exercises the shipped predicate
     * rather than a copy of it. Schema changes are guarded by hasColumn, so re-running
     * against an already-migrated test database is a no-op.
     */
    private function runBackfill(): void
    {
        $migration = include database_path(
            'migrations/2026_10_15_000001_add_picked_up_at_to_orders_table.php'
        );

        $migration->up();
    }
}
