<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['name' => 'Red',        'code' => 'RED', 'hex' => '#FF0000'],
            ['name' => 'Blue',       'code' => 'BLU', 'hex' => '#0000FF'],
            ['name' => 'Green',      'code' => 'GRN', 'hex' => '#008000'],
            ['name' => 'Yellow',     'code' => 'YLW', 'hex' => '#FFFF00'],
            ['name' => 'Pink',       'code' => 'PNK', 'hex' => '#FFC0CB'],
            ['name' => 'Purple',     'code' => 'PPL', 'hex' => '#800080'],
            ['name' => 'Orange',     'code' => 'ORG', 'hex' => '#FFA500'],
            ['name' => 'Black',      'code' => 'BLK', 'hex' => '#000000'],
            ['name' => 'White',      'code' => 'WHT', 'hex' => '#FFFFFF'],
            ['name' => 'Grey',       'code' => 'GRY', 'hex' => '#808080'],
            ['name' => 'Brown',      'code' => 'BRN', 'hex' => '#A52A2A'],
            ['name' => 'Beige',      'code' => 'BEG', 'hex' => '#F5F5DC'],
            ['name' => 'Navy',       'code' => 'NVY', 'hex' => '#000080'],
            ['name' => 'Teal',       'code' => 'TEL', 'hex' => '#008080'],
            ['name' => 'Olive',      'code' => 'OLV', 'hex' => '#808000'],
            ['name' => 'Gold',       'code' => 'GLD', 'hex' => '#FFD700'],
            ['name' => 'Silver',     'code' => 'SLV', 'hex' => '#C0C0C0'],
            ['name' => 'Multicolor', 'code' => 'MLT', 'hex' => null],
            ['name' => 'Patterned',  'code' => 'PAT', 'hex' => null],
        ];

        foreach ($colors as $color) {
            Color::firstOrCreate(
                ['name' => $color['name']],
                ['code' => $color['code'], 'hex' => $color['hex'], 'is_active' => true]
            );
        }
    }
}
