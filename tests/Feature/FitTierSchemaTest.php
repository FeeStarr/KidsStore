<?php

namespace Tests\Feature;

use App\Models\CustomCreation;
use App\Models\CustomOrder;
use App\Models\SizeChart;
use App\Models\SizeChartEntry;
use App\Models\SizeChartMeasurement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Sizing evidence has to keep three things apart for the whole life of an order:
 *
 *   - an untilered standard entry, where no tier has been assigned yet
 *   - a fit that was actually chosen (by the tailor or by the customer)
 *   - customer-supplied measurements, which remain the production basis
 *
 * The dangerous failure is collapsing the first two. Backfilling "medium" would
 * manufacture a choice nobody made, and that invented fact is exactly what turns a
 * fair sizing dispute into an unfavourable one.
 */
class FitTierSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function chart(): SizeChart
    {
        return SizeChart::create([
            'name' => 'Girls Frocks 2026',
            'default_basis' => 'finished_garment',
            'version' => 1,
            'is_active' => true,
        ]);
    }

    private function order(array $attributes = []): CustomOrder
    {
        $user = User::factory()->create();

        return CustomOrder::create(array_merge([
            'custom_order_number' => 'CO-FIT-'.uniqid(),
            'user_id' => $user->id,
            'item_type' => 'frock',
            'status' => 'pending',
            'child_name' => 'Amara',
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_STANDARD,
        ], $attributes));
    }

    // ---------------------------------------------------------------- null meaning

    public function test_an_entry_without_a_fit_stays_null_rather_than_defaulting(): void
    {
        $entry = SizeChartEntry::create([
            'size_chart_id' => $this->chart()->id,
            'label' => '2-3 years',
            'sort_order' => 1,
        ]);

        $this->assertNull($entry->fresh()->fit);
        $this->assertFalse($entry->hasFit());
        $this->assertDatabaseHas('size_chart_entries', [
            'id' => $entry->id,
            'fit' => null,
        ]);
    }

    public function test_an_order_without_a_selected_fit_stays_null(): void
    {
        $order = $this->order(['selected_chart_size' => '2-3 years']);

        $this->assertNull($order->fresh()->selected_chart_fit);
    }

    public function test_the_schema_declares_both_fit_columns_nullable_with_no_default(): void
    {
        $this->assertTrue(Schema::hasColumn('size_chart_entries', 'fit'));
        $this->assertTrue(Schema::hasColumn('custom_orders', 'selected_chart_fit'));

        foreach ([['size_chart_entries', 'fit'], ['custom_orders', 'selected_chart_fit']] as [$table, $column]) {
            $schema = config('database.connections.mysql.database');

            $isNullable = \DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $schema)
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->value('IS_NULLABLE');

            $this->assertSame('YES', $isNullable, "{$table}.{$column} must be nullable");

            // MariaDB reports the literal string 'NULL' where MySQL reports SQL NULL,
            // so both spellings count as "no default".
            $default = \DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $schema)
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->value('COLUMN_DEFAULT');

            $this->assertTrue(
                $default === null || $default === 'NULL',
                "{$table}.{$column} must have no default, found: ".var_export($default, true)
            );
        }
    }

    public function test_the_database_refuses_an_out_of_range_fit(): void
    {
        $this->expectException(QueryException::class);

        SizeChartEntry::create([
            'size_chart_id' => $this->chart()->id,
            'label' => '2-3 years',
            'fit' => 'enormous',
            'sort_order' => 1,
        ]);
    }

    // ------------------------------------------------------------- valid fit values

    public function test_every_valid_fit_is_accepted(): void
    {
        $chart = $this->chart();

        foreach (SizeChart::FITS as $fit) {
            $entry = SizeChartEntry::create([
                'size_chart_id' => $chart->id,
                'label' => '2-3 years',
                'fit' => $fit,
                'sort_order' => 1,
            ]);

            $this->assertSame($fit, $entry->fresh()->fit);
            $this->assertTrue($entry->hasFit());
            $this->assertSame(SizeChart::FIT_LABELS[$fit], $entry->fitLabel());
        }

        $this->assertSame(3, SizeChartEntry::where('size_chart_id', $chart->id)->count());
    }

    public function test_an_order_can_hold_an_explicitly_selected_fit(): void
    {
        $order = $this->order([
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => SizeChart::FIT_LARGE,
        ]);

        $this->assertSame('large', $order->fresh()->selected_chart_fit);
    }

    public function test_the_three_states_stay_distinguishable_side_by_side(): void
    {
        $chart = $this->chart();

        $untilered = SizeChartEntry::create([
            'size_chart_id' => $chart->id, 'label' => '4-5 years', 'sort_order' => 1,
        ]);
        $small = SizeChartEntry::create([
            'size_chart_id' => $chart->id, 'label' => '4-5 years', 'fit' => 'small', 'sort_order' => 2,
        ]);
        $large = SizeChartEntry::create([
            'size_chart_id' => $chart->id, 'label' => '4-5 years', 'fit' => 'large', 'sort_order' => 3,
        ]);

        // One age band, partial coverage: the tailor does not offer medium at this age.
        $this->assertCount(3, $chart->entries()->get());

        $this->assertSame('4-5 years', $untilered->displayLabel());
        $this->assertSame('4-5 years - Small fitting', $small->displayLabel());
        $this->assertSame('4-5 years - Large fitting', $large->displayLabel());

        // Coverage varies per age band: medium is simply absent at 4-5 years
        // rather than being filled in with a default.
        $this->assertFalse(
            SizeChartEntry::where('size_chart_id', $chart->id)->where('fit', 'medium')->exists()
        );
    }

    // ---------------------------------------------------------------- design flag

    public function test_the_confirmation_flag_defaults_to_false(): void
    {
        $creation = CustomCreation::create([
            'title' => 'Beaded Party Frock',
            'image_path' => 'creations/beaded-party.jpg',
            'age_range' => '4-6 years',
            'is_active' => true,
        ]);

        $this->assertFalse((bool) $creation->fresh()->requires_measurement_confirmation);
    }

    public function test_the_confirmation_flag_is_assignable(): void
    {
        $creation = CustomCreation::create([
            'title' => 'Layered Tulle Gown',
            'image_path' => 'creations/layered-tulle.jpg',
            'age_range' => '5-7 years',
            'is_active' => true,
            'requires_measurement_confirmation' => true,
        ]);

        $this->assertTrue((bool) $creation->fresh()->requires_measurement_confirmation);
    }

    // ------------------------------------------------------------ snapshot evidence

    public function test_the_snapshot_records_the_chosen_fit_and_not_an_invented_one(): void
    {
        $chart = $this->chart();
        $entry = SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '2-3 years',
            'fit' => 'medium',
            'sort_order' => 1,
        ]);
        SizeChartMeasurement::create([
            'size_chart_entry_id' => $entry->id,
            'measurement_type' => 'chest',
            'basis' => 'body',
            'min_value' => 54,
            'max_value' => 58,
            'unit' => 'cm',
        ]);

        $order = $this->order([
            'size_chart_id' => $chart->id,
            'size_chart_version' => $chart->version,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'medium',
        ]);

        $order->captureSizingSnapshot();
        $order->save();

        $snapshot = $order->fresh()->sizing_snapshot;

        $this->assertSame('medium', $snapshot['selected_fit']);
        $this->assertSame('Medium fitting', $snapshot['selected_fit_label']);
        $this->assertNotNull($snapshot['chart_figures']);
    }

    public function test_the_snapshot_leaves_the_fit_null_when_none_was_chosen(): void
    {
        $chart = $this->chart();
        SizeChartEntry::create([
            'size_chart_id' => $chart->id,
            'label' => '6-7 years',
            'sort_order' => 1,
        ]);

        $order = $this->order([
            'size_chart_id' => $chart->id,
            'size_chart_version' => $chart->version,
            'selected_chart_size' => '6-7 years',
        ]);

        $order->captureSizingSnapshot();
        $order->save();

        $snapshot = $order->fresh()->sizing_snapshot;

        $this->assertNull($snapshot['selected_fit']);
        $this->assertNull($snapshot['selected_fit_label']);
    }

    public function test_customer_measurements_survive_alongside_a_standard_selection(): void
    {
        $chart = $this->chart();

        $order = $this->order([
            'size_chart_id' => $chart->id,
            'selected_chart_size' => '2-3 years',
            'selected_chart_fit' => 'small',
        ]);

        $order->measurements()->create([
            'measurement_type' => 'chest',
            'measurement_value' => 55.5,
            'measurement_unit' => 'cm',
        ]);

        $order->captureSizingSnapshot();
        $order->save();

        $snapshot = $order->fresh()->sizing_snapshot;

        // A chosen fit and a customer's own measurements are different facts and
        // must both survive; neither may overwrite the other.
        $this->assertSame('small', $snapshot['selected_fit']);
        $this->assertSame(55.5, $snapshot['submitted_measurements'][0]['value']);
    }

    // --------------------------------------------------------- migration reversibility

    /**
     * Reversibility is proven against the disposable test database only. The
     * development database never sees a rollback: SafeMysqlConnection refuses to
     * DROP there, and forcing it through is what left custom_order_measurement_guides
     * half-migrated earlier.
     */
    public function test_the_migration_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require database_path(
            'migrations/2026_10_19_000001_add_fit_and_confirmation_fields.php'
        );

        $chart = $this->chart();
        SizeChartEntry::create([
            'size_chart_id' => $chart->id, 'label' => '2-3 years', 'fit' => 'small', 'sort_order' => 1,
        ]);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('size_chart_entries', 'fit'));
        $this->assertFalse(Schema::hasColumn('custom_orders', 'selected_chart_fit'));
        $this->assertFalse(Schema::hasColumn('custom_creations', 'requires_measurement_confirmation'));

        // The original (chart, label) uniqueness comes back on rollback.
        $this->expectException(UniqueConstraintViolationException::class);
        SizeChartEntry::create([
            'size_chart_id' => $chart->id, 'label' => '2-3 years', 'sort_order' => 1,
        ]);
    }

    public function test_the_migration_can_be_reapplied_after_a_rollback(): void
    {
        $migration = require database_path(
            'migrations/2026_10_19_000001_add_fit_and_confirmation_fields.php'
        );

        $migration->down();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('size_chart_entries', 'fit'));
        $this->assertTrue(Schema::hasColumn('custom_orders', 'selected_chart_fit'));
        $this->assertTrue(Schema::hasColumn('custom_creations', 'requires_measurement_confirmation'));

        $entry = SizeChartEntry::create([
            'size_chart_id' => $this->chart()->id, 'label' => '2-3 years', 'fit' => 'large', 'sort_order' => 1,
        ]);

        $this->assertSame('large', $entry->fresh()->fit);
    }
}
