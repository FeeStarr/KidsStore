<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomOrderCascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private const EXPECTED_CASCADE_FKS = [
        ['custom_orders', 'user_id', 'users'],
        ['custom_order_measurements', 'custom_order_id', 'custom_orders'],
        ['custom_order_customizations', 'custom_order_id', 'custom_orders'],
        ['custom_order_files', 'custom_order_id', 'custom_orders'],
        ['custom_order_quotes', 'custom_order_id', 'custom_orders'],
        ['custom_order_messages', 'custom_order_id', 'custom_orders'],
        ['custom_order_messages', 'sender_id', 'users'],
        ['custom_order_status_history', 'custom_order_id', 'custom_orders'],
        ['custom_order_qc_checks', 'custom_order_id', 'custom_orders'],
    ];

    private const EXPECTED_SET_NULL_FKS = [
        ['custom_order_files', 'uploaded_by', 'users'],
        ['custom_order_quotes', 'created_by', 'users'],
        ['custom_order_status_history', 'changed_by', 'users'],
        ['custom_order_qc_checks', 'checked_by', 'users'],
    ];

    private function user(string $role = User::ROLE_CUSTOMER): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function customOrderFor(User $customer): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-CASC-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Amara',
            'delivery_method' => 'delivery',
            'return_policy_acknowledged' => true,
        ]);
    }

    private function seedChildren(CustomOrder $order, User $sender): void
    {
        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 55.5,
            'measurement_unit' => 'cm',
        ]);

        $order->customizations()->create([
            'attribute' => 'dress_style',
            'value' => 'A-line',
        ]);

        $order->files()->create([
            'file_type' => 'reference_image',
            'file_path' => 'custom-orders/test/ref.png',
        ]);

        $order->quotes()->create([
            'version' => 1,
            'total' => 15000,
            'status' => 'draft',
            'created_by' => $sender->id,
        ]);

        $order->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => $sender->id,
            'message' => 'Hello from KidsFlairr.',
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        $order->statusHistory()->create([
            'old_status' => 'draft',
            'new_status' => 'submitted',
            'changed_by' => $sender->id,
            'created_at' => now(),
        ]);

        $order->qcChecks()->create([
            'check_item' => 'stitching',
            'passed' => null,
        ]);
    }

    private function childCounts(int $customOrderId): array
    {
        $tables = [
            'custom_order_measurements',
            'custom_order_customizations',
            'custom_order_files',
            'custom_order_quotes',
            'custom_order_messages',
            'custom_order_status_history',
            'custom_order_qc_checks',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) DB::table($table)->where('custom_order_id', $customOrderId)->count();
        }

        return $counts;
    }

    public function test_foreign_keys_use_expected_delete_rules(): void
    {
        $rows = DB::select("
            select kc.table_name as tbl, kc.column_name as col,
                   kc.referenced_table_name as ref_table, rc.delete_rule as rule
            from information_schema.key_column_usage kc
            join information_schema.referential_constraints rc
              on rc.constraint_schema = kc.constraint_schema
             and rc.constraint_name = kc.constraint_name
             and rc.table_name = kc.table_name
            where kc.constraint_schema = database()
              and kc.table_name in (
                  'custom_orders', 'custom_order_measurements', 'custom_order_customizations',
                  'custom_order_files', 'custom_order_quotes', 'custom_order_messages',
                  'custom_order_status_history', 'custom_order_qc_checks'
              )
              and kc.referenced_table_name in ('custom_orders', 'users')
        ");

        $found = [];
        foreach ($rows as $row) {
            $found["{$row->tbl}.{$row->col}->{$row->ref_table}"] = $row->rule;
        }

        foreach (self::EXPECTED_CASCADE_FKS as [$table, $column, $ref]) {
            $key = "{$table}.{$column}->{$ref}";
            $this->assertArrayHasKey($key, $found, "Missing foreign key {$key}");
            $this->assertSame('CASCADE', $found[$key], "Foreign key {$key} must be ON DELETE CASCADE but is {$found[$key]}");
        }

        foreach (self::EXPECTED_SET_NULL_FKS as [$table, $column, $ref]) {
            $key = "{$table}.{$column}->{$ref}";
            $this->assertArrayHasKey($key, $found, "Missing foreign key {$key}");
            $this->assertSame('SET NULL', $found[$key], "Foreign key {$key} must be ON DELETE SET NULL but is {$found[$key]}");
        }

        $this->assertCount(
            count(self::EXPECTED_CASCADE_FKS) + count(self::EXPECTED_SET_NULL_FKS),
            $found,
            'Unexpected extra foreign keys matched the query'
        );
    }

    public function test_deleting_custom_order_cascades_to_all_children_and_nulls_order_link(): void
    {
        $customer = $this->user();
        $admin = $this->user(User::ROLE_ADMIN);
        $order = $this->customOrderFor($customer);
        $this->seedChildren($order, $admin);

        $linkedOrder = Order::create([
            'reference' => 'ORD-CASC-' . uniqid(),
            'order_date' => now()->toDateString(),
            'customer_id' => $customer->id,
            'custom_order_id' => $order->id,
            'status' => 'confirmed',
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount' => 15000,
            'grand_total' => 15000,
            'amount_paid' => 15000,
        ]);

        $order->delete();

        $this->assertDatabaseMissing('custom_orders', ['id' => $order->id]);

        foreach ($this->childCounts($order->id) as $table => $count) {
            $this->assertSame(0, $count, "{$table} rows should cascade with the custom order");
        }

        $this->assertNull($linkedOrder->fresh()->custom_order_id, 'orders.custom_order_id should be SET NULL');
    }

    public function test_deleting_user_cascades_their_custom_orders_and_children(): void
    {
        $customer = $this->user();
        $admin = $this->user(User::ROLE_ADMIN);
        $order = $this->customOrderFor($customer);
        $this->seedChildren($order, $admin);

        $customer->delete();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseMissing('custom_orders', ['id' => $order->id]);

        foreach ($this->childCounts($order->id) as $table => $count) {
            $this->assertSame(0, $count, "{$table} rows should cascade with the custom order");
        }
    }

    public function test_deleting_sender_user_cascades_their_messages_only(): void
    {
        $customer = $this->user();
        $staff = $this->user(User::ROLE_ADMIN);
        $order = $this->customOrderFor($customer);

        $order->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => $staff->id,
            'message' => 'From staff.',
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        $order->messages()->create([
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'message' => 'From customer.',
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        $staff->delete();

        $this->assertDatabaseMissing('custom_order_messages', ['sender_id' => $staff->id]);
        $this->assertDatabaseHas('custom_order_messages', ['sender_id' => $customer->id]);
        $this->assertDatabaseHas('custom_orders', ['id' => $order->id]);
    }
}
