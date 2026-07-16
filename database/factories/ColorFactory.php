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
        $name = ucfirst(fake()->unique()->colorName());
        return [
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)),
            'hex' => fake()->safeHexColor(),
        ];
    }
}
