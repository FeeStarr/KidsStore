<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            // Idempotency key issued when the custom order form is rendered. The unique
            // index is what actually guarantees a replayed POST (double click, browser
            // or proxy retry) can never insert a second order. Scoped to the customer so
            // a token can never resolve to somebody else's order. MySQL treats NULLs as
            // distinct in a unique index, so legacy rows are unaffected.
            $table->string('submission_token', 64)->nullable()->after('custom_order_number');
            $table->unique(['user_id', 'submission_token'], 'custom_orders_user_submission_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropUnique('custom_orders_user_submission_token_unique');
            $table->dropColumn('submission_token');
        });
    }
};
