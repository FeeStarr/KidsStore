<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentVerification;
use App\Models\User;
use App\Notifications\AdminPaymentSubmittedNotification;
use App\Notifications\PaymentVerificationDelayedNotification;
use Illuminate\Support\Facades\DB;

class PaymentVerificationService
{
    /**
     * Station submits payment for admin verification.
     */
    public function submit(Order $order, int $stationId, ?string $note = null): PaymentVerification
    {
        $existing = $order->latestPendingVerification();
        if ($existing) {
            throw new \RuntimeException('A verification request is already pending for this order.');
        }

        $verification = DB::transaction(function () use ($order, $stationId, $note) {
            $order->update(['payment_status' => 'verification_pending']);

            return PaymentVerification::create([
                'order_id'          => $order->id,
                'pickup_station_id' => $stationId,
                'status'            => PaymentVerification::STATUS_PENDING,
                'station_note'      => $note,
                'submitted_by'      => auth()->id(),
                'submitted_at'      => now(),
            ]);
        });

        // Notify admins
        $admins = User::where('is_active', true)
            ->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN])
            ->get();

        foreach ($admins as $index => $admin) {
            $admin->notify(new AdminPaymentSubmittedNotification($order, $verification, $index === 0));
        }

        return $verification;
    }

    /**
     * Admin confirms payment.
     */
    public function confirm(Order $order, PaymentVerification $verification, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $verification, $note) {
            $verification->update([
                'status'      => PaymentVerification::STATUS_CONFIRMED,
                'admin_note'  => $note,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $order->update(['payment_status' => 'paid']);
        });
    }

    /**
     * Admin rejects payment.
     */
    public function reject(Order $order, PaymentVerification $verification, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $verification, $reason) {
            $verification->update([
                'status'      => PaymentVerification::STATUS_REJECTED,
                'admin_note'  => $reason,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $order->update(['payment_status' => 'verification_failed']);
        });
    }

    /**
     * Mark delayed verifications (40+ minutes without admin action).
     */
    public function markDelayed(): int
    {
        $cutoff = now()->subMinutes(40);

        $pending = PaymentVerification::where('status', PaymentVerification::STATUS_PENDING)
            ->where('submitted_at', '<=', $cutoff)
            ->whereNull('delay_notified_at')
            ->with('order')
            ->get();

        $count = 0;

        foreach ($pending as $verification) {
            DB::transaction(function () use ($verification) {
                $verification->update([
                    'status'            => PaymentVerification::STATUS_DELAYED,
                    'delay_notified_at' => now(),
                ]);
            });

            $admins = User::where('is_active', true)
                ->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN])
                ->get();

            foreach ($admins as $index => $admin) {
                $admin->notify(new PaymentVerificationDelayedNotification($verification->order, $verification, $index === 0));
            }

            $count++;
        }

        return $count;
    }
}
