<?php

namespace Database\Factories;

use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomOrderFactory extends Factory
{
    protected $model = CustomOrder::class;
    protected static int $counter = 1;

    public function definition(): array
    {
        return [
            'custom_order_number' => 'CF-' . str_pad(static::$counter++, 6, '0', STR_PAD_LEFT),
            'user_id' => User::factory(),
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_DRAFT,
            'payment_status' => 'unpaid',
            'child_name' => fake()->firstName('female'),
            'child_age' => fake()->numberBetween(1, 12),
            'child_gender' => 'girl',
            'delivery_method' => 'delivery',
            'delivery_address' => fake()->address(),
            'customer_notes' => null,
            'subtotal' => 0,
            'customization_fee' => 0,
            'rush_fee' => 0,
            'delivery_fee' => 0,
            'total_amount' => 0,
            'amount_paid' => 0,
            'return_policy_acknowledged' => true,
            'submitted_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => CustomOrder::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => CustomOrder::STATUS_COMPLETED,
            'submitted_at' => now()->subDays(30),
            'completed_at' => now(),
            'total_amount' => fake()->randomElement([15000, 25000, 35000, 50000]),
            'amount_paid' => fn (array $attr) => $attr['total_amount'],
            'payment_status' => 'paid',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => CustomOrder::STATUS_CANCELLED,
            'submitted_at' => now()->subDays(10),
            'cancelled_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => CustomOrder::STATUS_REJECTED,
            'submitted_at' => now()->subDays(5),
        ]);
    }

    public function withPrice(float $total = 25000, float $paid = 0): static
    {
        return $this->state(fn () => [
            'total_amount' => $total,
            'amount_paid' => $paid,
            'payment_status' => $paid >= $total ? 'paid' : 'unpaid',
        ]);
    }
}
