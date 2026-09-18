<?php

namespace Database\Factories;

use App\Models\CustomCreation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomCreationFactory extends Factory
{
    protected $model = CustomCreation::class;

    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);
        $title = ucwords($title);

        return [
            'title' => $title,
            'image_path' => 'custom-creations/' . fake()->uuid() . '.jpg',
            'price' => fake()->randomElement([null, 25000, 35000, 45000, 55000]),
            'is_price_from' => fake()->boolean(30),
            'description' => fake()->optional(0.8)->sentence(10),
            'category' => fake()->randomElement(array_keys(CustomCreation::CATEGORIES)),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withPrice(float $price = 35000): static
    {
        return $this->state(fn () => ['price' => $price]);
    }

    public function noImage(): static
    {
        return $this->state(fn () => ['image_path' => null]);
    }
}
