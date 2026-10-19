<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fit tiers, and a per-design measurement-confirmation flag.
     *
     * A size is chosen by age band plus how the frock is fitted: Small / Medium /
     * Large *fitting*. Those describe the garment, never the child's body.
     *
     * Both fit columns are deliberately nullable with NO default. A row that predates
     * tiers genuinely has no tier assigned, and writing "medium" would record a choice
     * nobody made - which is exactly the kind of invented evidence that turns a fair
     * sizing dispute into an unfavourable one. The distinction that must survive the
     * whole lifecycle is:
     *
     *   fit IS NULL            - an untilered standard entry, tier not yet assigned
     *   fit = small|medium|large - a fit the tailor or the customer explicitly chose
     *   measurements supplied  - customer measurements, which remain the production basis
     *
     * Coverage varies per age band: a fit the tailor does not support for that band is
     * simply absent, and that is fine. Only a fit that is offered must carry complete
     * measurements, which is enforced at publication rather than here.
     */
    public function up(): void
    {
        Schema::table('size_chart_entries', function (Blueprint $table) {
            $table->enum('fit', ['small', 'medium', 'large'])
                ->nullable()
                ->after('label');

            // Fit is part of an entry's identity. A band must be able to hold
            // "2-3 years - Small" and "2-3 years - Medium" side by side, each with its
            // own measurements, which the old (chart, label) index forbade outright.
            //
            // Caveat carried into the application layer: MySQL treats NULLs as
            // distinct, so this index cannot stop two untilered rows sharing a label.
            // That rule is enforced by the admin editor and the publish gate instead.
            $table->dropUnique('size_chart_entries_size_chart_id_label_unique');
            $table->unique(
                ['size_chart_id', 'label', 'fit'],
                'size_chart_entries_chart_label_fit_unique'
            );
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->enum('selected_chart_fit', ['small', 'medium', 'large'])
                ->nullable()
                ->after('selected_chart_size');
        });

        Schema::table('custom_creations', function (Blueprint $table) {
            // Per-design flag: expensive or intricate designs ask the parent to
            // re-check their child's current measurements before production, while
            // standard designs stay a simple confirm-the-sizing-basis step.
            $table->boolean('requires_measurement_confirmation')
                ->default(false)
                ->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('custom_creations', function (Blueprint $table) {
            $table->dropColumn('requires_measurement_confirmation');
        });

        Schema::table('custom_orders', function (Blueprint $table) {
            $table->dropColumn('selected_chart_fit');
        });

        Schema::table('size_chart_entries', function (Blueprint $table) {
            $table->dropUnique('size_chart_entries_chart_label_fit_unique');
            $table->dropColumn('fit');

            $table->unique(['size_chart_id', 'label']);
        });
    }
};
