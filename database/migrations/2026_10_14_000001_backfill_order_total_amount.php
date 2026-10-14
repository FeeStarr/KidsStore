<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill total_amount for orders where it was never written.
     *
     * total_amount is a legacy duplicate of grand_total. The regular-order flow keeps
     * both in step (OrderService::recalculateTotals), but the custom-order flow only
     * ever set grand_total, leaving total_amount at 0. Anything deriving money from
     * total_amount then reported a fully paid order as owing -15,000.
     *
     * Scoped deliberately to total_amount = 0 AND grand_total > 0, so it cannot touch
     * a row that already carries a real total. Rows are only aligned when
     * grand_total is the authoritative figure - verify that against production first.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('total_amount', 0)
            ->where('grand_total', '>', 0)
            ->update(['total_amount' => DB::raw('grand_total')]);
    }

    /**
     * Intentionally irreversible. A backfilled row is indistinguishable from one that
     * always had the correct total_amount, so reverting would corrupt rows that were
     * already correct. Restore from a backup instead.
     */
    public function down(): void
    {
        //
    }
};
