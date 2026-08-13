<?php

namespace App\Console\Commands;

use App\Services\DealService;
use Illuminate\Console\Command;

class SyncDealStatuses extends Command
{
    protected $signature = 'deals:sync-status';

    protected $description = 'Activate scheduled deals and expire finished deals based on the clock.';

    public function handle(DealService $deals): int
    {
        $updated = $deals->syncStatuses();

        $this->info("Deal statuses synced. {$updated} deal(s) updated.");

        return self::SUCCESS;
    }
}
