<?php

namespace Database\Seeders;

use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            '66','73','80','90','100','110','120','130','140','150','160','170'
        ];

        // Ensure only these sizes exist: truncate, reset auto-increment and re-insert
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('sizes')->truncate();
        DB::statement('ALTER TABLE sizes AUTO_INCREMENT = 1');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($sizes as $name) {
            Size::create([
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
