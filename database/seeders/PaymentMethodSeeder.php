<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['key' => 'pay_now', 'label' => 'Pay Now', 'is_active' => true],
            ['key' => 'pay_on_delivery', 'label' => 'Pay on Delivery', 'is_active' => true],
            ['key' => 'pay_at_pickup', 'label' => 'Pay at Pickup', 'is_active' => false],
            ['key' => 'instant_bank_transfer', 'label' => 'Instant Bank Transfer', 'is_active' => false],
            ['key' => 'transfer', 'label' => 'Bank Transfer (Manual)', 'is_active' => false],
        ];

        foreach ($methods as $m) {
            PaymentMethod::firstOrCreate(['key' => $m['key']], $m);
        }
    }
}
