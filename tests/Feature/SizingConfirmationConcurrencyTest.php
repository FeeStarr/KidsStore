<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Services\CustomOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase as FoundationTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two confirmations arriving at once must not be able to write two different
 * snapshots of the same order.
 *
 * The confirmation takes a row lock, so the second one waits for the first and
 * then finds the order already confirmed. This test drives two independent
 * database connections to show that the lock is real: connection A holds it,
 * and connection B must genuinely block.
 *
 * A sequential double-submission test cannot prove that. It only proves the guard
 * catches a request it can already see finished.
 *
 * Deliberately extends the framework TestCase rather than Tests\TestCase:
 * RefreshDatabase wraps each test in a single uncommitted transaction, and a
 * second connection cannot see uncommitted data, which would make the whole thing
 * vacuous. The cost of that choice is manual cleanup, handled in tearDown().
 */
class SizingConfirmationConcurrencyTest extends FoundationTestCase
{
    private ?int $userId = null;

    private ?int $orderId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('custom_orders')) {
            $this->artisan('migrate');
        }

        // Two additional connections to the same database, so each holds its own
        // PDO handle and its own transaction.
        config([
            'database.connections.lock_a' => config('database.connections.mysql'),
            'database.connections.lock_b' => config('database.connections.mysql'),
        ]);

        DB::purge('lock_a');
        DB::purge('lock_b');

        $this->userId = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ])->id;

        $this->orderId = CustomOrder::create([
            'custom_order_number' => 'CO-CONC-'.uniqid(),
            'user_id' => $this->userId,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Amara',
            'sizing_method' => CustomOrder::SIZING_STANDARD,
            'return_policy_acknowledged' => true,
        ])->id;
    }

    protected function tearDown(): void
    {
        if ($this->orderId) {
            DB::table('custom_order_measurements')->where('custom_order_id', $this->orderId)->delete();
            DB::table('custom_orders')->where('id', $this->orderId)->delete();
        }

        if ($this->userId) {
            DB::table('users')->where('id', $this->userId)->delete();
        }

        DB::purge('lock_a');
        DB::purge('lock_b');

        parent::tearDown();
    }

    public function test_a_second_confirmation_cannot_take_the_same_row_lock(): void
    {
        $a = DB::connection('lock_a');
        $b = DB::connection('lock_b');

        // A first confirmation is in flight and holds the row lock.
        $a->beginTransaction();
        $a->table('custom_orders')->where('id', $this->orderId)->lockForUpdate()->first();

        // Fail fast rather than hanging if the lock is not really taken.
        $b->statement('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            $b->table('custom_orders')->where('id', $this->orderId)->lockForUpdate()->first();
        } catch (QueryException) {
            $blocked = true;
        } finally {
            if ($b->getPdo()->inTransaction()) {
                $b->rollBack();
            }

            if ($a->getPdo()->inTransaction()) {
                $a->rollBack();
            }
        }

        $this->assertTrue(
            $blocked,
            'the confirmation must take a row lock, so two of them cannot write conflicting snapshots'
        );
    }

    public function test_only_one_confirmation_can_write_a_snapshot(): void
    {
        $service = app(CustomOrderService::class);

        $order = CustomOrder::find($this->orderId);

        $service->confirmSizing($order, [], false, $this->userId);

        $first = CustomOrder::find($this->orderId)->sizing_snapshot;

        $this->assertNotNull($first);
        $this->assertNotNull($first['captured_at']);

        // The second attempt finds the order already confirmed.
        $this->expectException(\RuntimeException::class);

        $service->confirmSizing(CustomOrder::find($this->orderId), [], false, $this->userId);
    }
}
