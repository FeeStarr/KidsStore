<?php

namespace Database\Factories;

use App\Models\AgeRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgeRange>
 */
class AgeRangeFactory extends Factory
{
    protected $model = AgeRange::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            'is_active' => true,
        ];
    }
}
