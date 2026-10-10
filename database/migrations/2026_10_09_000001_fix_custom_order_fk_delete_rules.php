<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original create-table migrations called the non-existent
     * ->cascadeDelete() (silently swallowed by Fluent), so MySQL created
     * these foreign keys with the default ON DELETE RESTRICT. Drop and
     * re-add them with a real ON DELETE CASCADE.
     */
    private const FOREIGN_KEYS = [
        ['custom_orders', ['user_id'], 'users'],
        ['custom_order_measurements', ['custom_order_id'], 'custom_orders'],
        ['custom_order_customizations', ['custom_order_id'], 'custom_orders'],
        ['custom_order_files', ['custom_order_id'], 'custom_orders'],
        ['custom_order_quotes', ['custom_order_id'], 'custom_orders'],
        ['custom_order_messages', ['custom_order_id'], 'custom_orders'],
        ['custom_order_messages', ['sender_id'], 'users'],
        ['custom_order_status_history', ['custom_order_id'], 'custom_orders'],
        ['custom_order_qc_checks', ['custom_order_id'], 'custom_orders'],
    ];

    public function up(): void
    {
        foreach (self::FOREIGN_KEYS as [$table, $columns, $on]) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $on) {
                $blueprint->dropForeign($columns);
                $blueprint->foreign($columns)->references('id')->on($on)->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::FOREIGN_KEYS as [$table, $columns, $on]) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $on) {
                $blueprint->dropForeign($columns);
                $blueprint->foreign($columns)->references('id')->on($on);
            });
        }
    }
};
