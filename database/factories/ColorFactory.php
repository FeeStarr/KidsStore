<?php

namespace Database\Factories;

use App\Models\Color;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Color>
 */
class ColorFactory extends Factory
{
    protected $model = Color::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->colorName()),
            'hex' => '#' . substr(md5(fake()->unique()->word()), 0, 6),
        ];
    }
}
