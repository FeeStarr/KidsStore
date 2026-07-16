<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add nullable code column first so we can populate values safely
        Schema::table('colors', function (Blueprint $table) {
            $table->string('code', 5)->nullable()->after('name');
        });

        // Populate codes and hex values
        DB::table('colors')->where('name', 'Red')->update(['code' => 'RED', 'hex' => '#FF0000']);
        DB::table('colors')->where('name', 'Blue')->update(['code' => 'BLU', 'hex' => '#0000FF']);
        DB::table('colors')->where('name', 'Green')->update(['code' => 'GRN', 'hex' => '#008000']);
        DB::table('colors')->where('name', 'Yellow')->update(['code' => 'YLW', 'hex' => '#FFFF00']);
        DB::table('colors')->where('name', 'Pink')->update(['code' => 'PNK', 'hex' => '#FFC0CB']);
        DB::table('colors')->where('name', 'Purple')->update(['code' => 'PPL', 'hex' => '#800080']);
        DB::table('colors')->where('name', 'Orange')->update(['code' => 'ORG', 'hex' => '#FFA500']);
        DB::table('colors')->where('name', 'Black')->update(['code' => 'BLK', 'hex' => '#000000']);
        DB::table('colors')->where('name', 'White')->update(['code' => 'WHT', 'hex' => '#FFFFFF']);
        DB::table('colors')->where('name', 'Grey')->update(['code' => 'GRY', 'hex' => '#808080']);
        DB::table('colors')->where('name', 'Brown')->update(['code' => 'BRN', 'hex' => '#A52A2A']);
        DB::table('colors')->where('name', 'Beige')->update(['code' => 'BEG', 'hex' => '#F5F5DC']);
        DB::table('colors')->where('name', 'Navy')->update(['code' => 'NVY', 'hex' => '#000080']);
        DB::table('colors')->where('name', 'Teal')->update(['code' => 'TEL', 'hex' => '#008080']);
        DB::table('colors')->where('name', 'Olive')->update(['code' => 'OLV', 'hex' => '#808000']);
        DB::table('colors')->where('name', 'Gold')->update(['code' => 'GLD', 'hex' => '#FFD700']);
        DB::table('colors')->where('name', 'Silver')->update(['code' => 'SLV', 'hex' => '#C0C0C0']);
        DB::table('colors')->where('name', 'Multicolor')->update(['code' => 'MLT', 'hex' => null]);
        DB::table('colors')->where('name', 'Patterned')->update(['code' => 'PAT', 'hex' => null]);

        // Fill any remaining NULL/empty codes with a sanitized, unique fallback
        $rows = DB::table('colors')->whereNull('code')->orWhere('code', '')->get();
        foreach ($rows as $r) {
            $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($r->name ?? 'C', 0, 5)));
            if ($base === '') {
                $base = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            }
            $candidate = $base;
            $i = 0;
            while (DB::table('colors')->where('code', $candidate)->exists()) {
                $i++;
                $candidate = substr($base, 0, 5 - strlen((string) $i)) . (string) $i;
            }
            DB::table('colors')->whereKey($r->id)->update(['code' => $candidate]);
        }

        // Make column NOT NULL and add unique constraint
        // Use raw statements to avoid requiring doctrine/dbal for column modification
        DB::statement("ALTER TABLE `colors` MODIFY `code` VARCHAR(5) NOT NULL");
        Schema::table('colors', function (Blueprint $table) {
            $table->unique('code', 'uq_colors_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('colors', function (Blueprint $table) {
            $table->dropUnique('uq_colors_code');
            $table->dropColumn('code');
        });
    }
};
