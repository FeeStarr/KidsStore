<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['key' => 'cash', 'label' => 'Cash', 'is_active' => false],
            ['key' => 'card', 'label' => 'Card', 'is_active' => false],
            ['key' => 'transfer', 'label' => 'Bank transfer', 'is_active' => true],
            ['key' => 'mobile', 'label' => 'Mobile money', 'is_active' => false],
            ['key' => 'other', 'label' => 'Other', 'is_active' => false],
        ];

        foreach ($methods as $m) {
            PaymentMethod::firstOrCreate(['key' => $m['key']], $m);
        }
    }
}
