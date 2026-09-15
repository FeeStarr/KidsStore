<?php

namespace Database\Seeders;

use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeliveryAgentAccountSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding delivery agent accounts...');

        $agents = DeliveryAgent::all();

        foreach ($agents as $agent) {
            // Skip if already linked to a user
            if ($agent->user_id) {
                $this->command->info("  Agent {$agent->name} already has a user account. Skipping.");
                continue;
            }

            $tempPassword = Str::random(12);

            $user = User::create([
                'name'                => $agent->name,
                'email'               => $agent->email ?? strtolower(str_replace(' ', '.', $agent->name)) . '@kidsflairr.com',
                'password'            => Hash::make($tempPassword),
                'phone'               => $agent->phone,
                'role'                => User::ROLE_DELIVERY_AGENT,
                'is_active'           => $agent->is_active,
                'must_change_password'=> true,
            ]);

            $agent->update([
                'account_number' => 'DA-' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
                'user_id'        => $user->id,
            ]);

            $this->command->info("  Created user for {$agent->name} ({$agent->account_number}) — temp password: {$tempPassword}");
        }

        // Normalize existing delivery orders
        $updated = Order::where('delivery_method', 'delivery')
            ->whereNotNull('delivery_agent_id')
            ->whereNull('delivery_status')
            ->update(['delivery_status' => 'pending']);

        $this->command->info("  Normalized {$updated} existing delivery orders to status 'pending'.");
    }
}
