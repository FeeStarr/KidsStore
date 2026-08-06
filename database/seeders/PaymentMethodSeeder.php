<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['key' => 'pay_at_pickup', 'label' => 'Pay at Pickup', 'is_active' => true],
            ['key' => 'instant_bank_transfer', 'label' => 'Instant Bank Transfer', 'is_active' => true],
            ['key' => 'cash', 'label' => 'Cash', 'is_active' => false],
            ['key' => 'card', 'label' => 'Card', 'is_active' => false],
            ['key' => 'transfer', 'label' => 'Bank Transfer (Manual)', 'is_active' => false],
            ['key' => 'mobile', 'label' => 'Mobile Money', 'is_active' => false],
        ];

        foreach ($methods as $m) {
            PaymentMethod::firstOrCreate(['key' => $m['key']], $m);
        }
    }
}
