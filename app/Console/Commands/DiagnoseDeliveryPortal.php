<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseDeliveryPortal extends Command
{
    protected $signature = 'delivery:diagnose {email : Email of the delivery portal account}';
    protected $description = 'Diagnose delivery portal login failures through the app DB connection';

    public function handle(): int
    {
        $email = $this->argument('email');

        $this->line('DB: ' . DB::connection()->getDatabaseName());

        $ran = DB::table('migrations')->pluck('migration')->all();
        $pending = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($file) => basename($file, '.php'))
            ->diff($ran)
            ->values();
        $this->line($pending->isEmpty() ? 'Migrations: all ran' : 'Migrations PENDING: ' . $pending->implode(', '));

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user row in app DB for [{$email}]");
            return self::FAILURE;
        }

        $this->line("User #{$user->id} role={$user->role} is_active=" . var_export((bool) $user->is_active, true));

        $agent = $user->deliveryAgent;
        $this->line('Relation -> ' . ($agent
            ? "agents #{$agent->id} user_id={$agent->user_id} is_active=" . var_export((bool) $agent->is_active, true)
            : 'NULL'));

        $rows = DB::table('delivery_agents')->where('user_id', $user->id)->orWhere('email', $email)->get();
        $this->line('Agent rows matching user_id OR email:');
        foreach ($rows as $row) {
            $this->line("  agents #{$row->id} user_id=" . ($row->user_id ?? 'NULL') . " email={$row->email} is_active={$row->is_active}");
        }
        if ($rows->isEmpty()) {
            $this->line('  (none)');
        }

        $fails = [];
        if ($user->role !== User::ROLE_DELIVERY_AGENT) {
            $fails[] = 'role is not delivery_agent -> "Invalid credentials."';
        }
        if (! $user->is_active) {
            $fails[] = 'user is_active=0 -> "Invalid credentials."';
        }
        if (! $agent) {
            $fails[] = 'no agent linked to this user -> "Your account is inactive."';
        } elseif (! $agent->is_active) {
            $fails[] = 'agent is_active=0 -> "Your account is inactive."';
        }

        $this->newLine();
        if ($fails) {
            foreach ($fails as $fail) {
                $this->error('FAIL: ' . $fail);
            }

            return self::FAILURE;
        }

        $this->info('Login checks PASS - portal login should work');
        return self::SUCCESS;
    }
}
