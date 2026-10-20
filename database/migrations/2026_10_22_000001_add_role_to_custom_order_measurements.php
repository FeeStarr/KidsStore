<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Measurements now record what they are for.
     *
     * A production measurement is what the tailor cuts to. A comparison measurement is
     * what the parent supplied so it can be set against the standard figures. They may
     * hold the same numbers, but they are not the same thing, and conflating them
     * would let a comparison figure quietly become the cutting basis.
     *
     * The unique key gains the role so the same measurement type can exist once as
     * production and once as comparison. Existing rows are production measurements,
     * which is what they always were.
     */
    public function up(): void
    {
        Schema::table('custom_order_measurements', function (Blueprint $table) {
            $table->enum('role', ['production', 'comparison'])
                ->default('production')
                ->after('measurement_unit');

            // The foreign key on custom_order_id uses the old unique index as its
            // supporting index, so give it a plain one before that index goes.
            $table->index('custom_order_id', 'co_measurement_custom_order_id_index');

            $table->dropUnique('co_measurement_unique');
            $table->unique(['custom_order_id', 'measurement_type', 'role'], 'co_measurement_type_role_unique');
        });
    }

    public function down(): void
    {
        Schema::table('custom_order_measurements', function (Blueprint $table) {
            // Comparison measurements are removed rather than folded back into the
            // production role: a comparison figure was never a cutting basis.
            $table->where('role', 'comparison')->delete();

            $table->dropUnique('co_measurement_type_role_unique');
            $table->dropColumn('role');

            $table->dropIndex('co_measurement_custom_order_id_index');
            $table->unique(['custom_order_id', 'measurement_type'], 'co_measurement_unique');
        });
    }
};
