<?php

namespace App\Console\Commands;

use App\Helpers\BusinessDayHelper;
use App\Models\RefundRequest;
use App\Notifications\DropoffReminderNotification;
use App\Notifications\SlaEscalationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckReturnSla extends Command
{
    protected $signature = 'returns:check-sla';
    protected $description = 'Check SLA status for return requests and send escalation notifications';

    public function handle(): int
    {
        $this->info('Checking return SLA status...');

        $reviewWarning   = 0;
        $reviewBreached  = 0;
        $inspWarning     = 0;
        $inspBreached    = 0;
        $dropoffReminder = 0;
        $dropoffFinal    = 0;

        // ── 1. Review SLA (1-6 business days) ────────────────────────────────
        // Warning at day 5, breached at day 6
        $reviewRequests = RefundRequest::whereIn('status', [
            RefundRequest::STATUS_REQUESTED,
            RefundRequest::STATUS_PENDING_REVIEW,
            RefundRequest::STATUS_AWAITING_EVIDENCE,
        ])
            ->whereNotNull('review_deadline')
            ->where('review_sla_breached', false)
            ->get();

        foreach ($reviewRequests as $rr) {
            $daysUsed = BusinessDayHelper::businessDaysBetween($rr->created_at, now());

            if ($daysUsed >= 6 && ! $rr->review_sla_breached) {
                // Breached
                $rr->update(['review_sla_breached' => true]);
                $this->notifyAdmins($rr, 'review', 'breached');
                $reviewBreached++;
                Log::info("SLA BREACHED: Review for Return #{$rr->id} ({$daysUsed} business days)");
            } elseif ($daysUsed >= 5) {
                // Warning - only notify once (check if last audit log is not already a review warning)
                $lastWarning = $rr->auditLogs()
                    ->where('action', 'sla_review_warning')
                    ->whereDate('created_at', today())
                    ->exists();

                if (! $lastWarning) {
                    $this->notifyAdmins($rr, 'review', 'warning');
                    $this->logSlaAudit($rr, 'sla_review_warning', "Day {$daysUsed} of 6 - warning");
                    $reviewWarning++;
                }
            }
        }

        // ── 2. Inspection SLA (2-5 business days) ────────────────────────────
        // Warning at day 4, breached at day 5
        $inspectionRequests = RefundRequest::where('status', RefundRequest::STATUS_RECEIVED)
            ->whereNotNull('inspection_deadline')
            ->where('inspection_sla_breached', false)
            ->get();

        foreach ($inspectionRequests as $rr) {
            $daysUsed = BusinessDayHelper::businessDaysBetween($rr->inspected_at ?? $rr->created_at, now());

            if ($daysUsed >= 5 && ! $rr->inspection_sla_breached) {
                $rr->update(['inspection_sla_breached' => true]);
                $this->notifyAdmins($rr, 'inspection', 'breached');
                $inspBreached++;
                Log::info("SLA BREACHED: Inspection for Return #{$rr->id} ({$daysUsed} business days)");
            } elseif ($daysUsed >= 4) {
                $lastWarning = $rr->auditLogs()
                    ->where('action', 'sla_inspection_warning')
                    ->whereDate('created_at', today())
                    ->exists();

                if (! $lastWarning) {
                    $this->notifyAdmins($rr, 'inspection', 'warning');
                    $this->logSlaAudit($rr, 'sla_inspection_warning', "Day {$daysUsed} of 5 - warning");
                    $inspWarning++;
                }
            }
        }

        // ── 3. Drop-off SLA (3 business days after approval) ─────────────────
        // Reminder on day 2, final warning on day 3 (breached)
        $approvedRequests = RefundRequest::whereIn('status', [
            RefundRequest::STATUS_APPROVED,
            RefundRequest::STATUS_AWAITING_SHIPMENT,
        ])
            ->whereNotNull('dropoff_deadline')
            ->where('dropoff_sla_breached', false)
            ->get();

        foreach ($approvedRequests as $rr) {
            $daysUsed = BusinessDayHelper::businessDaysBetween($rr->reviewed_at ?? $rr->created_at, now());

            if ($daysUsed >= 3 && ! $rr->dropoff_sla_breached) {
                // Final day - breach
                $rr->update(['dropoff_sla_breached' => true]);
                $this->notifyCustomerDropoff($rr, 'final');
                $this->logSlaAudit($rr, 'sla_dropoff_breached', "Day {$daysUsed} of 3 - drop-off deadline breached");
                $dropoffFinal++;
                Log::info("SLA BREACHED: Drop-off for Return #{$rr->id} ({$daysUsed} business days)");
            } elseif ($daysUsed >= 2) {
                // Reminder
                $lastReminder = $rr->auditLogs()
                    ->where('action', 'sla_dropoff_reminder')
                    ->whereDate('created_at', today())
                    ->exists();

                if (! $lastReminder) {
                    $this->notifyCustomerDropoff($rr, 'reminder');
                    $this->logSlaAudit($rr, 'sla_dropoff_reminder', "Day {$daysUsed} of 3 - reminder sent");
                    $dropoffReminder++;
                }
            }
        }

        $this->info("SLA Check Complete:");
        $this->info("  Review: {$reviewWarning} warnings, {$reviewBreached} breached");
        $this->info("  Inspection: {$inspWarning} warnings, {$inspBreached} breached");
        $this->info("  Drop-off: {$dropoffReminder} reminders, {$dropoffFinal} final warnings");

        return Command::SUCCESS;
    }

    private function notifyAdmins(RefundRequest $rr, string $slaType, string $urgency): void
    {
        try {
            $admins = \App\Notifications\NotificationRecipients::adminUsers();
            foreach ($admins as $admin) {
                $admin->notify(new SlaEscalationNotification($rr, $slaType, $urgency));
            }

            $support = \App\Notifications\NotificationRecipients::customerSupportStaff();
            foreach ($support as $staff) {
                $staff->notify(new SlaEscalationNotification($rr, $slaType, $urgency));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send SLA escalation for Return #{$rr->id}: {$e->getMessage()}");
        }
    }

    private function notifyCustomerDropoff(RefundRequest $rr, string $urgency): void
    {
        try {
            $customer = $rr->order->customer;
            if ($customer) {
                $customer->notify(new DropoffReminderNotification($rr, $urgency));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send drop-off reminder for Return #{$rr->id}: {$e->getMessage()}");
        }
    }

    private function logSlaAudit(RefundRequest $rr, string $action, string $details): void
    {
        try {
            \App\Models\ReturnAuditLog::create([
                'refund_request_id' => $rr->id,
                'action'            => $action,
                'user_id'           => null,
                'details'           => $details,
                'metadata'          => ['sla_check' => true],
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to log SLA audit for Return #{$rr->id}: {$e->getMessage()}");
        }
    }
}
