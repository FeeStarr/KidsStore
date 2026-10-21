<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\Order;
use App\Notifications\CustomQuoteApproved;
use App\Notifications\CustomQuoteExpired;
use App\Notifications\CustomQuoteReady;
use App\Notifications\NotificationRecipients;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

  class CustomQuoteService
  {
      /**
       * Why the customer could not be emailed about their quote, if they could not.
       *
       * The quote is created either way, but the admin has to be told that the
       * customer was not reached, rather than being told they were. A quote
       * waiting for approval that the customer never knew about is a request that
       * simply stops.
       */
      public ?string $customerNotificationError = null;

      public function __construct(
          private CustomOrderService $customOrderService,
          private CustomPaymentService $paymentService,
      ) {}

    public function create(
        CustomOrder $order,
        array $breakdown,
        float $total,
        ?string $notes,
        int $validDays = 7,
        ?int $userId = null,
    ): CustomOrderQuote {
        $quote = DB::transaction(function () use ($order, $breakdown, $total, $notes, $validDays, $userId) {
            // Supersede any existing draft quote
            $order->quotes()
                ->where('status', CustomOrderQuote::STATUS_DRAFT)
                ->update(['status' => CustomOrderQuote::STATUS_SUPERSEDED]);

            $nextVersion = ($order->quotes()->max('version') ?? 0) + 1;

            $quote = CustomOrderQuote::create([
                'custom_order_id' => $order->id,
                'version' => $nextVersion,
                'base_price' => $breakdown['base_price'] ?? 0,
                'fabric_cost' => $breakdown['fabric_cost'] ?? 0,
                'customization_cost' => $breakdown['customization_cost'] ?? 0,
                'embellishment_cost' => $breakdown['embellishment_cost'] ?? 0,
                'measurement_fee' => $breakdown['measurement_fee'] ?? 0,
                'rush_fee' => $breakdown['rush_fee'] ?? 0,
                'delivery_fee' => $breakdown['delivery_fee'] ?? 0,
                'discount' => $breakdown['discount'] ?? 0,
                'total' => $total,
                'breakdown' => $this->buildBreakdownArray($breakdown),
                'valid_until' => Carbon::now()->addDays($validDays),
                'notes' => $notes,
                'status' => CustomOrderQuote::STATUS_DRAFT,
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $this->customOrderService->transitionTo(
                $order,
                CustomOrder::STATUS_QUOTED,
                $userId
            );

            $order->update([
                'total_amount' => $total,
                'quote_valid_until' => $quote->valid_until,
            ]);

            return $quote;
        });

        // Sent outside the transaction on purpose. Inside it, any mail failure (SMTP
        // timeout, bad credentials) propagated and rolled the whole quote back, so a
        // flaky mail server could silently discard the admin's work.
        $this->customerNotificationError = null;

        try {
            $order->user->notify(new CustomQuoteReady($order, $quote));
        } catch (\Throwable $e) {
            $this->customerNotificationError = $e->getMessage();

            Log::error('CustomQuoteReady notification failed', [
                'error' => $e->getMessage(),
                'order' => $order->custom_order_number,
                'quote' => $quote->id,
                'email' => $order->user?->email,
            ]);
        }

        return $quote;
    }

    /**
     * Approve the customer's quote and create the payable linked order as one unit.
     *
     * Everything that makes the customer *commit* to an order happens inside a single
     * transaction: the quote is marked approved, the lifecycle advances to
     * customer_approved, the linked order is created, and the lifecycle reaches
     * payment_pending. If any step throws, all of it rolls back and the record is left
     * exactly as it was - no approved quote, no orphaned order, nothing to pay for.
     *
     * The custom-order row is locked for update *inside* the transaction, so two
     * concurrent approvals serialise rather than both passing the "already approved"
     * check. That plus the unique index on orders.custom_order_id means a customer can
     * only ever end up with one payable order.
     */
    public function approveAndCreateOrder(CustomOrder $order, ?int $userId = null): Order
    {
        $linkedOrder = DB::transaction(function () use ($order, $userId) {
            // Re-read under the row lock. The route-bound instance was loaded before
            // this transaction began and carries a stale status.
            $locked = CustomOrder::lockForUpdate()->find($order->id);

            if (! $locked) {
                throw new RuntimeException('Custom order not found.');
            }

            if ($locked->order()->exists()) {
                throw new RuntimeException('This quote has already been approved.');
            }

            $quote = $locked->approvedQuote() ?? $locked->latestQuote();

            if (! $quote || $quote->isExpired()) {
                throw new RuntimeException('This quote has expired and cannot be approved.');
            }

            if ($quote->status !== CustomOrderQuote::STATUS_DRAFT) {
                throw new RuntimeException('Only draft quotes can be approved.');
            }

            if ($quote->custom_order_id !== $locked->id) {
                throw new RuntimeException('Quote does not belong to this custom order.');
            }

            // Supersede any other draft quotes so only one can ever be approved.
            $locked->quotes()
                ->where('id', '!=', $quote->id)
                ->where('status', CustomOrderQuote::STATUS_DRAFT)
                ->update(['status' => CustomOrderQuote::STATUS_SUPERSEDED]);

            $quote->update([
                'status' => CustomOrderQuote::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

            $this->customOrderService->transitionTo(
                $locked,
                CustomOrder::STATUS_CUSTOMER_APPROVED,
                $userId
            );

            $linked = $this->paymentService->createLinkedOrder($locked);

            $this->customOrderService->transitionTo(
                $locked->fresh(),
                CustomOrder::STATUS_PAYMENT_PENDING,
                $userId
            );

            return $linked;
        });

        // Dispatched only once the transaction has committed. A rollback cannot unsend
        // an email, so a notification must never live inside the transaction.
        Notification::send(
            NotificationRecipients::adminUsers(),
            new CustomQuoteApproved($order->fresh())
        );

        return $linkedOrder;
    }

    public function requestRevision(CustomOrder $order, string $message, ?int $userId = null): void
    {
        $this->customOrderService->transitionTo(
            $order,
            CustomOrder::STATUS_NEEDS_REVISION,
            $userId,
            $message
        );
    }

    public function checkExpiry(): int
    {
        $expired = CustomOrder::where('status', CustomOrder::STATUS_QUOTED)
            ->whereNotNull('quote_valid_until')
            ->where('quote_valid_until', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $order) {
            $this->customOrderService->transitionTo(
                $order,
                CustomOrder::STATUS_QUOTE_EXPIRED
            );

            // Tell the customer their quote lapsed so they can start a new request,
            // instead of the order silently going quiet.
            try {
                $order->user->notify(new CustomQuoteExpired($order));
            } catch (\Throwable $e) {
                Log::error('CustomQuoteExpired notification failed', [
                    'error' => $e->getMessage(),
                    'order' => $order->custom_order_number,
                    'email' => $order->user?->email,
                ]);
            }

            $count++;
        }

        return $count;
    }

    public function calculateSuggestedTotal(CustomOrder $order): float
    {
        $fabricCost = 0;
        $customizationCost = 0;
        $embellishmentCost = 0;

        $fabric = $order->getCustomizationValue('fabric');
        if ($fabric) {
            $fabricCost = 3500; // Default - admin overrides
        }

        $customizations = $order->customizations()->pluck('value')->count();
        $customizationCost = max(0, ($customizations - 1)) * 500; // ₦500 per extra option

        $embellishments = $order->getCustomizationValue('embellishments');
        if ($embellishments) {
            $embellishmentCost = 2000; // Default
        }

        $measurementFee = $order->measurements()->count() > 0 ? 1000 : 0;

        $basePrice = 12000;

        return $basePrice + $fabricCost + $customizationCost + $embellishmentCost + $measurementFee;
    }

    private function buildBreakdownArray(array $breakdown): array
    {
        $items = [];
        $labels = [
            'base_price' => 'Frock — base garment',
            'fabric_cost' => 'Fabric — selected fabric',
            'customization_cost' => 'Design & Customization — modifications/special design requests',
            'embellishment_cost' => 'Embellishments — decorative additions',
            'measurement_fee' => 'Custom Measurements — made to the customer\'s measurements',
            'rush_fee' => 'Rush Fee',
            'delivery_fee' => 'Delivery',
            'discount' => 'Discount',
        ];

        foreach ($labels as $key => $label) {
            $amount = $breakdown[$key] ?? 0;
            if ($key === 'discount') {
                $amount = -$amount;
            }
            if ($amount != 0) {
                $items[] = ['label' => $label, 'amount' => $amount];
            }
        }

        return $items;
    }
}
