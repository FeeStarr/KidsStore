<?php

namespace App\Console\Commands;

use App\Services\PaymentVerificationService;
use Illuminate\Console\Command;

class CheckPaymentVerifications extends Command
{
    protected $signature = 'payments:check-delayed';
    protected $description = 'Mark payment verifications that have exceeded the 40-minute window';

    public function handle(PaymentVerificationService $service): int
    {
        $count = $service->markDelayed();

        if ($count === 0) {
            $this->info('No delayed payment verifications.');
        } else {
            $this->warn("Marked {$count} verification(s) as delayed and notified admins.");
        }

        return Command::SUCCESS;
    }
}
