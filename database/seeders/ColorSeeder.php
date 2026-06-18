<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            'Red','Blue','Green','Yellow','Pink','Purple','Orange','Black','White','Grey',
            'Brown','Beige','Navy','Teal','Olive','Gold','Silver','Multicolor','Patterned'
        ];

        foreach ($colors as $name) {
            Color::firstOrCreate([
                'name' => $name,
            ], [
                'is_active' => true,
            ]);
        }
    }
}
