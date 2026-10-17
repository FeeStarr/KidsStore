<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Size charts for custom frocks.
     *
     * A chart is versioned and admin-editable. Nothing here ships with numbers: the
     * tailor supplies the measurements, so a chart with no measurement rows must never
     * be publishable or selectable.
     *
     * Body and finished-garment measurements are kept distinct because they are not
     * interchangeable. A parent measuring their child's chest against a finished-garment
     * chart will pick the wrong size, which is exactly the dispute this exists to
     * prevent. basis is stored per measurement row - a chart may legitimately list
     * both side by side - while default_basis is only a convenience for data entry.
     */
    public function up(): void
    {
        Schema::create('size_charts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Convenience default for new measurement rows; the row's own basis wins.
            $table->enum('default_basis', ['body', 'finished_garment'])->default('body');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            // Set once an order references this version, so recorded sizing evidence
            // cannot be altered afterwards. Edits must create a new version.
            $table->boolean('is_locked')->default(false);
            $table->foreignId('locked_by_order_id')->nullable()
                ->constrained('custom_orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_published', 'is_active']);
        });

        Schema::create('size_chart_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_chart_id')->constrained('size_charts')->cascadeOnDelete();
            $table->string('label');            // e.g. "2-3 years"
            $table->string('code')->nullable();  // e.g. "2-3Y"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['size_chart_id', 'label']);
            $table->index(['size_chart_id', 'sort_order']);
        });

        Schema::create('size_chart_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_chart_entry_id')->constrained('size_chart_entries')->cascadeOnDelete();
            $table->string('measurement_type', 64);
            $table->enum('basis', ['body', 'finished_garment']);
            // Ranges are optional: the tailor may supply a single figure, or none yet.
            $table->decimal('min_value', 8, 2)->nullable();
            $table->decimal('max_value', 8, 2)->nullable();
            $table->string('unit', 8)->default('cm');
            $table->timestamps();

            // Composite key: the same measurement type may appear once per basis for a
            // given size, which a uniqueness constraint on measurement_type alone would
            // wrongly forbid.
            $table->unique(
                ['size_chart_entry_id', 'measurement_type', 'basis'],
                'size_chart_measurement_unique'
            );
        });

        // How-to-measure instructions must match the basis of the figure they sit
        // beside. The seeded copy is all body measurement, so it becomes the body row.
        Schema::table('custom_order_measurement_guides', function (Blueprint $table) {
            $table->enum('basis', ['body', 'finished_garment'])->default('body')->after('measurement_type');
        });

        Schema::table('custom_order_measurement_guides', function (Blueprint $table) {
            $table->dropUnique('custom_order_measurement_guides_measurement_type_unique');
            $table->unique(
                ['measurement_type', 'basis'],
                'cmg_measurement_type_basis_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('custom_order_measurement_guides', function (Blueprint $table) {
            $table->dropUnique('cmg_measurement_type_basis_unique');
            $table->dropColumn('basis');
        });

        Schema::table('custom_order_measurement_guides', function (Blueprint $table) {
            $table->unique('measurement_type', 'custom_order_measurement_guides_measurement_type_unique');
        });

        // Children first: measurements -> entries -> charts.
        Schema::dropIfExists('size_chart_measurements');
        Schema::dropIfExists('size_chart_entries');
        Schema::dropIfExists('size_charts');
    }
};
