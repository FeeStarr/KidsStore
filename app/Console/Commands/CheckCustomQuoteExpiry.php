<?php

namespace App\Console\Commands;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\User;
use App\Notifications\CustomQuoteExpiryReminder;
use App\Services\CustomQuoteService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckCustomQuoteExpiry extends Command
{
    protected $signature = 'custom-quotes:check-expiry';
    protected $description = 'Check for expired custom quotes and send expiry reminders';

    public function handle(CustomQuoteService $quoteService): int
    {
        // Check for expired quotes
        $expiredCount = $quoteService->checkExpiry();

        if ($expiredCount > 0) {
            $this->info("Marked {$expiredCount} quote(s) as expired.");
        }

        // Send reminders for quotes expiring within 24 hours
        $expiringSoon = CustomOrder::where('status', CustomOrder::STATUS_QUOTED)
            ->whereNotNull('quote_valid_until')
            ->where('quote_valid_until', '>', now())
            ->where('quote_valid_until', '<=', now()->addHours(24))
            ->get();

        foreach ($expiringSoon as $order) {
            $latestQuote = $order->latestQuote();
            if ($latestQuote && !$latestQuote->reminder_sent) {
                $order->user->notify(new CustomQuoteExpiryReminder($order, $latestQuote));
                $latestQuote->update(['reminder_sent' => true]);
                $this->info("Sent expiry reminder for order {$order->custom_order_number}");
            }
        }

        $this->info('Quote expiry check completed.');
        return Command::SUCCESS;
    }
}
