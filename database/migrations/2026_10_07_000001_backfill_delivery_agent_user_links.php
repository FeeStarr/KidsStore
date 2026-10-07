<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link pre-existing delivery agents to their user accounts.
     *
     * delivery_agents.user_id was added nullable (2026_09_15_000001) with no
     * backfill, so agents created before that migration have no link. Portal
     * login requires $user->deliveryAgent, so those accounts fail with
     * "Your account is inactive." even when the agent row is active.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_agents', 'user_id')) {
            return;
        }

        $links = DB::table('delivery_agents')
            ->join('users', 'users.email', '=', 'delivery_agents.email')
            ->whereNull('delivery_agents.user_id')
            ->where('users.role', 'delivery_agent')
            ->select('delivery_agents.id as agent_id', 'users.id as user_id')
            ->get();

        foreach ($links as $link) {
            $taken = DB::table('delivery_agents')
                ->where('user_id', $link->user_id)
                ->exists();

            if (! $taken) {
                DB::table('delivery_agents')
                    ->where('id', $link->agent_id)
                    ->update(['user_id' => $link->user_id]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: un-linking would re-break portal logins.
    }
};
