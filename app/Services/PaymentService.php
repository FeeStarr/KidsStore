<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates payment recording and order payment-status updates.
 */
class PaymentService
{
    public function __construct(private OrderService $orders)
    {
    }

    /**
     * @param array{
     *   payment_date: string,
     *   amount: float,
     *   method?: string,
     *   transaction_id?: string|null,
     *   note?: string|null,
     *   reference?: string
     * } $data
     */
    public function record(Order $order, array $data): Payment
    {
        return DB::transaction(function () use ($order, $data) {
            $payment = $order->payments()->create([
                'reference'      => $data['reference'] ?? $this->generateReference(),
                'payment_date'   => $data['payment_date'],
                'amount'         => (float) $data['amount'],
                'method'         => $data['method'] ?? 'cash',
                'transaction_id' => $data['transaction_id'] ?? null,
                'note'           => $data['note'] ?? null,
            ]);

            $this->orders->recordPayment($order, (float) $data['amount']);

            return $payment;
        });
    }

    public function generateReference(): string
    {
        $next = (Payment::max('id') ?? 0) + 1;

        return 'PAY-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
