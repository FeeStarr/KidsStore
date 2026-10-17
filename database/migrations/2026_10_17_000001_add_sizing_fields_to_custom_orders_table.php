<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sizing method and the chart a request was sized against.
     *
     * standard_size: the parent picked a size from a published chart after comparing
     * their child's measurements with it.
     * custom_measurements: the parent supplied the measurements themselves.
     *
     * nullable because existing requests predate this and must not be forced into a
     * sizing method they never chose. Those records simply have no sizing evidence.
     */
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->enum('sizing_method', ['standard_size', 'custom_measurements'])
                ->nullable()->after('child_gender');

            // nullOnDelete: retiring a chart must not delete a customer's request.
            $table->foreignId('size_chart_id')->nullable()
                ->constrained('size_charts')->nullOnDelete()->after('sizing_method');
            $table->unsignedInteger('size_chart_version')->nullable()->after('size_chart_id');
            $table->string('selected_chart_size', 64)->nullable()->after('size_chart_version');

            // Frozen copy of what the chart showed at the time of confirmation, so later
            // chart edits cannot change the evidence attached to an existing order.
            $table->json('sizing_snapshot')->nullable()->after('selected_chart_size');

            $table->timestamp('sizing_confirmed_at')->nullable()->after('sizing_snapshot');
            $table->timestamp('sizing_policy_acknowledged_at')->nullable()->after('sizing_confirmed_at');
            $table->timestamp('return_policy_acknowledged_at')->nullable()->after('sizing_policy_acknowledged_at');

            $table->index(['sizing_method', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropIndex(['sizing_method', 'status']);
            $table->dropConstrainedForeignId('size_chart_id');
            $table->dropColumn([
                'sizing_method', 'size_chart_version', 'selected_chart_size',
                'sizing_snapshot', 'sizing_confirmed_at',
                'sizing_policy_acknowledged_at', 'return_policy_acknowledged_at',
            ]);
        });
    }
};