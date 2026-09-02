<?php

namespace App\Console\Commands;

use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Console\Command;

class CheckRefundStatus extends Command
{
    protected $signature = 'refunds:check-status {--limit=50}';
    protected $description = 'Poll Paystack for refunds stuck in refund_processing (uses refund_processing_at, not created_at)';

    public function handle(RefundService $refunds): int
    {
        $limit = (int) $this->option('limit');
        $pending = RefundRequest::where('status', RefundRequest::STATUS_REFUND_PROCESSING)
            ->where('refund_processing_at', '<=', now()->subHour())
            ->where(function ($q) {
                $q->whereNull('last_refund_check_at')
                  ->orWhere('last_refund_check_at', '<=', now()->subHour());
            })
            ->limit($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No refunds to check.');
            return 0;
        }

        foreach ($pending as $rr) {
            $this->line("Checking refund #{$rr->id} - {$rr->order->reference} - {$rr->provider_refund_reference}");
            try {
                $refunds->syncRefundStatus($rr);
                $this->info("  -> {$rr->fresh()->status}");
            } catch (\Throwable $e) {
                $this->error("  failed: {$e->getMessage()}");
            }
        }

        return 0;
    }
}
