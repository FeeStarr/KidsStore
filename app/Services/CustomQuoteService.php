<?php

namespace App\Services;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\User;
use App\Notifications\CustomQuoteReady;
use App\Notifications\CustomQuoteApproved;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class CustomQuoteService
{
    public function __construct(
        private CustomOrderService $customOrderService,
    ) {}

    public function create(
        CustomOrder $order,
        array $breakdown,
        float $total,
        ?string $notes,
        int $validDays = 7,
        ?int $userId = null,
    ): CustomOrderQuote {
        return DB::transaction(function () use ($order, $breakdown, $total, $notes, $validDays, $userId) {
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

            // Notify customer
            $order->user->notify(new CustomQuoteReady($order, $quote));

            return $quote;
        });
    }

    public function approve(CustomOrder $order, CustomOrderQuote $quote, ?int $userId = null): void
    {
        if ($quote->custom_order_id !== $order->id) {
            throw new RuntimeException('Quote does not belong to this custom order.');
        }

        if ($quote->status !== CustomOrderQuote::STATUS_DRAFT) {
            throw new RuntimeException('Only draft quotes can be approved.');
        }

        if ($quote->isExpired()) {
            throw new RuntimeException('This quote has expired and cannot be approved.');
        }

        DB::transaction(function () use ($order, $quote, $userId) {
            // Supersede other draft quotes
            $order->quotes()
                ->where('id', '!=', $quote->id)
                ->where('status', CustomOrderQuote::STATUS_DRAFT)
                ->update(['status' => CustomOrderQuote::STATUS_SUPERSEDED]);

            $quote->update([
                'status' => CustomOrderQuote::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

            $this->customOrderService->transitionTo(
                $order,
                CustomOrder::STATUS_CUSTOMER_APPROVED,
                $userId
            );

            // Notify admins
            $admins = User::role(['superadmin', 'admin'])->get();
            Notification::send($admins, new CustomQuoteApproved($order));
        });
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
            'base_price' => 'Base Frock',
            'fabric_cost' => 'Fabric',
            'customization_cost' => 'Customization',
            'embellishment_cost' => 'Embellishment',
            'measurement_fee' => 'Custom Measurement',
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
