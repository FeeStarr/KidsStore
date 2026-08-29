<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\PickupReminderNotification;
use Illuminate\Console\Command;

class SendPickupReminders extends Command
{
    protected $signature = 'pickups:send-reminders';
    protected $description = 'Send pickup reminder emails on Day 2, 3, and 4 of the collection window';

    public function handle(): int
    {
        $this->info('Checking for orders that need pickup reminders...');

        $orders = Order::where('status', 'ready for pick up')
            ->whereHas('items', function ($q) {
                $q->where('pickup_status', 'ready for pickup');
            })
            ->whereNotNull('ready_for_pickup_at')
            ->with('customer', 'pickupStation')
            ->get();

        $sent = 0;

        foreach ($orders as $order) {
            $daysElapsed = $order->ready_for_pickup_at->diffInDays(now());

            if ($daysElapsed < 2 || $daysElapsed > 4) {
                continue;
            }

            $message = match ($daysElapsed) {
                2 => null,
                3 => 'Your pickup window expires tomorrow. Please collect your order as soon as possible.',
                4 => 'Today is the LAST DAY to collect your order. If not collected today, the order will be marked as expired.',
                default => null,
            };

            $subject = match ($daysElapsed) {
                2 => "Reminder: Your order is ready for pickup - {$order->reference}",
                3 => "Action Required: Your pickup window expires tomorrow - {$order->reference}",
                4 => "Last Day: Collect your order today - {$order->reference}",
                default => "Pickup Reminder - {$order->reference}",
            };

            if ($order->customer) {
                $order->customer->notify(new PickupReminderNotification($order, $message, $subject));
                $sent++;
                $this->line("  <info>{$order->reference}</info> - Day {$daysElapsed} reminder sent to {$order->customer->email}");
            }
        }

        $this->newLine();
        $this->info("Done. {$sent} reminder(s) sent.");

        return Command::SUCCESS;
    }
}
