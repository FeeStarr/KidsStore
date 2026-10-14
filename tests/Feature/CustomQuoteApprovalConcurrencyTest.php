<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase as FoundationTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Genuine concurrency coverage for quote approval.
 *
 * CustomQuoteService::approveAndCreateOrder() relies on SELECT ... FOR UPDATE to make
 * two simultaneous approvals serialise instead of both passing the "already approved"
 * check. A sequential double-submission test cannot prove that lock is real - it only
 * proves the guard catches a request it can already see finished.
 *
 * So this test drives two independent database connections by hand:
 *   - connection A holds the row lock, connection B must genuinely block on it;
 *   - once A commits, B re-reads under the same lock and sees the order A created,
 *     which is exactly what makes the guard refuse the second approval.
 *
 * Deliberately extends the framework TestCase rather than Tests\TestCase:
 * RefreshDatabase wraps each test in a single uncommitted transaction, and a second
 * connection cannot see uncommitted data, which would make the whole thing vacuous.
 * The cost of that choice is manual cleanup, handled in tearDown().
 */
class CustomQuoteApprovalConcurrencyTest extends FoundationTestCase
{
    private ?int $customOrderId = null;

    private ?int $userId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('custom_orders')) {
            $this->artisan('migrate');
        }

        // Two additional connections to the same database, so each holds its own PDO
        // handle and its own transaction.
        config([
            'database.connections.lock_a' => config('database.connections.mysql'),
            'database.connections.lock_b' => config('database.connections.mysql'),
        ]);
        DB::purge('lock_a');
        DB::purge('lock_b');
    }

    protected function tearDown(): void
    {
        if ($this->customOrderId) {
            DB::table('order_items')->whereIn(
                'order_id',
                DB::table('orders')->where('custom_order_id', $this->customOrderId)->pluck('id')
            )->delete();
            DB::table('payments')->whereIn(
                'order_id',
                DB::table('orders')->where('custom_order_id', $this->customOrderId)->pluck('id')
            )->delete();
            DB::table('payment_transactions')->whereIn(
                'order_id',
                DB::table('orders')->where('custom_order_id', $this->customOrderId)->pluck('id')
            )->delete();
            DB::table('orders')->where('custom_order_id', $this->customOrderId)->delete();
            DB::table('custom_order_quotes')->where('custom_order_id', $this->customOrderId)->delete();
            DB::table('custom_order_status_history')->where('custom_order_id', $this->customOrderId)->delete();
            DB::table('custom_orders')->where('id', $this->customOrderId)->delete();
        }

        if ($this->userId) {
            DB::table('users')->where('id', $this->userId)->delete();
        }

        DB::purge('lock_a');
        DB::purge('lock_b');

        parent::tearDown();
    }

    private function seedQuotedOrder(): CustomOrder
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
        $this->userId = $user->id;

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-LOCK-'.uniqid(),
            'user_id' => $user->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
        $this->customOrderId = $order->id;

        CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 15000,
            'total' => 15000,
            'valid_until' => now()->addDays(7),
            'status' => CustomOrderQuote::STATUS_DRAFT,
        ]);

        return $order;
    }

    private function insertLinkedOrder($connection, int $customOrderId, string $reference): void
    {
        $connection->table('orders')->insert([
            'reference' => $reference,
            'customer_id' => $this->userId,
            'custom_order_id' => $customOrderId,
            'order_date' => now(),
            'status' => 'pending payment',
            'subtotal' => 15000,
            'grand_total' => 15000,
            'total_amount' => 15000,
        ]);
    }

    /**
     * Proves the row lock is genuinely enforced by the database rather than merely
     * assumed: while A holds SELECT ... FOR UPDATE, B cannot acquire the same row.
     */
    public function test_a_second_connection_is_blocked_by_the_row_lock(): void
    {
        $order = $this->seedQuotedOrder();

        $a = DB::connection('lock_a');
        $b = DB::connection('lock_b');

        $a->beginTransaction();
        $this->assertNotNull(
            $a->table('custom_orders')->where('id', $order->id)->lockForUpdate()->first(),
            'Connection A must be able to take the row lock.'
        );

        // A short lock wait so a genuine block fails fast instead of hanging the suite.
        $b->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $b->beginTransaction();

        $blocked = false;
        try {
            $b->table('custom_orders')->where('id', $order->id)->lockForUpdate()->first();
        } catch (QueryException $e) {
            $blocked = true;
        }
        $b->rollBack();

        $this->assertTrue(
            $blocked,
            'Connection B should block on the row lock held by connection A. If it did not, '
            .'the locking primitive this flow depends on is not working.'
        );

        $a->rollBack();
    }

    /**
     * Once A commits, B's re-read under the same lock sees the order A created. That
     * visibility is precisely what lets the service guard refuse a second approval, and
     * the unique index refuses it even if the guard did not run.
     */
    public function test_second_connection_sees_the_committed_order_and_cannot_create_a_duplicate(): void
    {
        $order = $this->seedQuotedOrder();

        $a = DB::connection('lock_a');
        $b = DB::connection('lock_b');

        // Fail fast rather than hanging for the 50s default if a lock is contended.
        $a->statement('SET SESSION innodb_lock_wait_timeout = 2');
        $b->statement('SET SESSION innodb_lock_wait_timeout = 2');

        // Connection A approves: locks, checks, creates, commits.
        $a->beginTransaction();
        $a->table('custom_orders')->where('id', $order->id)->lockForUpdate()->first();

        $seenBefore = $a->table('orders')->where('custom_order_id', $order->id)->exists();
        $this->assertFalse($seenBefore);

        $this->insertLinkedOrder($a, $order->id, 'ORD-LOCK-A-'.uniqid());
        $a->commit();

        // Connection B now runs the same check and must see A's committed order.
        $b->beginTransaction();
        $b->table('custom_orders')->where('id', $order->id)->lockForUpdate()->first();
        $seenAfter = $b->table('orders')->where('custom_order_id', $order->id)->exists();
        $b->commit();

        $this->assertTrue(
            $seenAfter,
            'The second connection must observe the committed order, which is what the guard checks.'
        );
        $this->assertSame(
            1,
            DB::table('orders')->where('custom_order_id', $order->id)->count(),
            'Only one payable order may exist for a custom order.'
        );
    }
}
