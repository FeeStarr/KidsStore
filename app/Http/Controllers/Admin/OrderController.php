<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\AuditLog;
use App\Models\DeliveryAgent;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments
    ) {
    }

    public function index(): View
    {
        $orders = Order::with('customer')->latest()->limit(2000)->get();

        return view('admin.orders.index', compact('orders'));
    }

    public function create(): View
    {
        $customers = User::where('role', User::ROLE_CUSTOMER)
            ->orderBy('name')
            ->get();
        $products  = Product::with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'), 'variants.inventory'])
            ->where('is_active', true)->orderBy('name')->get();

        return view('admin.orders.create', compact('customers', 'products'));
    }

    public function store(OrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->validated());

        return redirect()->route('admin.orders.show', $order)
            ->with('success', 'Order created.');
    }

    public function show(Order $order): View
    {
        $order->load('items.product', 'items.variant', 'payments', 'customer', 'pickupStation', 'paymentTransactions');

        return view('admin.orders.show', compact('order'));
    }

    public function confirm(Order $order): RedirectResponse
    {
        $this->orders->confirm($order);

        return back()->with('success', 'Order confirmed. Inventory updated.');
    }

    public function pendingConfirmation(Order $order): RedirectResponse
    {
        $this->orders->markPendingConfirmation($order);

        return back()->with('success', 'Order marked as pending confirmation.');
    }

    public function processing(Order $order): RedirectResponse
    {
        $this->orders->markProcessing($order);

        return back()->with('success', 'Order marked as processing.');
    }

    public function ship(Order $order): RedirectResponse
    {
        $this->orders->markShipped($order);

        return back()->with('success', 'Order marked as shipped.');
    }

    public function shippingToStation(Order $order): RedirectResponse
    {
        $this->orders->markShippingToStation($order);

        return back()->with('success', 'Order marked as shipping to station.');
    }

    public function readyForPickup(Order $order): RedirectResponse
    {
        $this->orders->markReadyForPickup($order);

        return back()->with('success', 'Order marked as ready for pick up.');
    }

    public function deliver(Order $order): RedirectResponse
    {
        $this->orders->markDelivered($order);

        return back()->with('success', 'Order marked as delivered.');
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->orders->cancel($order);

        return back()->with('success', 'Order cancelled. If paid, a refund request was queued for review.');
    }

    public function cancelItem(Request $request, Order $order, \App\Models\OrderItem $item): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000']]);
        $this->orders->cancelItem($order, $item, (int) $data['quantity']);
        return back()->with('success', 'Item cancelled - refund queued if paid.');
    }

    public function storePayment(PaymentRequest $request, Order $order): RedirectResponse
    {
        $this->payments->record($order, $request->validated());

        return back()->with('success', 'Payment recorded.');
    }

    public function updateDeliveryDate(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'expected_delivery_date' => ['required', 'date', 'after_or_equal:' . $order->order_date->toDateString()],
        ]);

        $order->update(['expected_delivery_date' => $request->expected_delivery_date]);

        return back()->with('success', 'Expected delivery date updated.');
    }

    public function updateCourier(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'courier_name'    => ['required', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url'    => ['nullable', 'url', 'max:500'],
        ]);

        $order->update([
            'courier_name'    => $request->courier_name,
            'tracking_number' => $request->tracking_number ?: null,
            'tracking_url'    => $request->tracking_url ?: null,
        ]);

        return back()->with('success', 'Courier information saved.');
    }

    public function markPaid(Order $order): RedirectResponse
    {
        $this->orders->recordPayment($order, (float) $order->grand_total);

        // Confirm the order if it was pending payment
        $order->refresh();
        if ($order->status === 'pending payment') {
            app(\App\Services\OrderService::class)->confirm($order);
        }

        return back()->with('success', 'Order marked as paid.');
    }

    public function confirmPayment(Order $order): RedirectResponse
    {
        $verification = $order->latestPendingVerification();

        if (! $verification) {
            return back()->with('error', 'No pending verification found for this order.');
        }

        $data = request()->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        app(\App\Services\PaymentVerificationService::class)->confirm(
            $order,
            $verification,
            $data['admin_note'] ?? null,
        );

        return back()->with('success', 'Payment confirmed. Station can now release the order.');
    }

    public function rejectPayment(Order $order): RedirectResponse
    {
        $verification = $order->latestPendingVerification();

        if (! $verification) {
            return back()->with('error', 'No pending verification found for this order.');
        }

        $data = request()->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        app(\App\Services\PaymentVerificationService::class)->reject(
            $order,
            $verification,
            $data['admin_note'] ?? null,
        );

        return back()->with('success', 'Payment rejected. Customer must retry payment.');
    }

    public function confirmUnderReview(Order $order): RedirectResponse
    {
        if ($order->payment_status !== 'under_review') {
            return back()->with('error', 'This order is not under review.');
        }

        $data = request()->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $txn = $order->paymentTransactions()->where('status', 'under_review')->latest()->first();
        if ($txn) {
            $txn->update(['status' => 'success']);
        }

        // amount_paid was already set by the webhook/payment flow; just mark as paid
        $order->update([
            'payment_status' => 'paid',
        ]);

        // Confirm the order if it was pending payment
        if ($order->status === 'pending payment') {
            app(\App\Services\OrderService::class)->confirm($order);
        }

        return back()->with('success', 'Payment confirmed. Order is now paid.');
    }

    public function rejectUnderReview(Order $order): RedirectResponse
    {
        if ($order->payment_status !== 'under_review') {
            return back()->with('error', 'This order is not under review.');
        }

        $data = request()->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $txn = $order->paymentTransactions()->where('status', 'under_review')->latest()->first();
        if ($txn) {
            $payload = (array) $txn->opay_payload;
            $payload['rejection_reason'] = $data['admin_note'] ?? 'Payment rejected by admin';
            $txn->update(['status' => 'failed', 'opay_payload' => $payload]);
        }

        $order->update(['payment_status' => 'unpaid']);

        return back()->with('success', 'Payment rejected. Customer can retry payment.');
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        if (in_array($order->status, ['delivered', 'cancelled'])) {
            return back()->with('error', 'Cannot change status of a ' . $order->getStatusLabel() . ' order.');
        }

        $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', $order->getAvailableStatuses())],
        ]);

        $newStatus = $request->status;
        $current = $order->status;

        if ($newStatus === $current) {
            return back()->with('info', 'Order is already ' . $order->getStatusLabel() . '.');
        }

        $methodMap = [
            'ordered'              => null,
            'pending confirmation' => 'markPendingConfirmation',
            'confirmed'            => 'confirm',
            'processing'           => 'markProcessing',
            'shipping to station'  => 'markShippingToStation',
            'out for delivery'     => 'markShipped',
            'ready for pick up'    => 'markReadyForPickup',
            'delivered'            => 'markDelivered',
            'cancelled'            => 'cancel',
            'pickup window expired' => 'markPickupWindowExpired',
        ];

        $method = $methodMap[$newStatus] ?? null;

        if ($method && method_exists($this->orders, $method)) {
            $this->orders->{$method}($order);
        } else {
            $order->update(['status' => $newStatus]);
        }

        return back()->with('success', 'Order status updated to ' . ucfirst($newStatus) . '.');
    }

    public function approveDelivery(Order $order): RedirectResponse
    {
        abort_unless($order->delivery_method === 'delivery', 400);
        abort_unless($order->delivery_agent_id, 400);
        abort_unless(in_array($order->delivery_status, [null, 'pending']), 400);

        $order->update([
            'delivery_status'      => 'assigned',
            'delivery_released_at' => now(),
        ]);

        AuditLog::create([
            'user_id'        => Auth::id(),
            'action'         => 'delivery.approved',
            'auditable_type' => Order::class,
            'auditable_id'   => $order->id,
            'meta'           => json_encode(['delivery_agent_id' => $order->delivery_agent_id]),
            'ip'             => request()->ip(),
        ]);

        return back()->with('success', "Delivery for {$order->reference} released to agent.");
    }

    public function reassignAgent(Request $request, Order $order): RedirectResponse
    {
        $status = $order->delivery_status;
        $isRerelease = ($status === 'failed');
        $isReassign  = in_array($status, [null, 'pending', 'assigned']);

        abort_unless($isRerelease || $isReassign, 400, 'Cannot reassign at this stage.');

        $rules = [
            'delivery_agent_id' => ['required', 'exists:delivery_agents,id'],
        ];

        // Reason required for assigned reassignment and failed re-release
        // Optional for pending (never released)
        if ($status === 'assigned' || $status === 'failed') {
            $rules['reason'] = ['required', 'string', 'max:500'];
        } else {
            $rules['reason'] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);

        $newAgent = DeliveryAgent::findOrFail($data['delivery_agent_id']);
        abort_unless($newAgent->is_active, 400, 'Selected agent is not active.');

        $previousAgentId = $order->delivery_agent_id;

        $updates = ['delivery_agent_id' => $newAgent->id];

        if ($status === 'failed') {
            $updates['delivery_status'] = 'pending';
            $updates['delivery_released_at'] = null;
            $updates['delivery_issue_reason'] = null;
            $updates['delivery_issue_notes'] = null;
        } elseif ($status === 'assigned') {
            $updates['delivery_status'] = 'pending';
            $updates['delivery_released_at'] = null;
        }

        $order->update($updates);

        AuditLog::create([
            'user_id'        => Auth::id(),
            'action'         => $isRerelease ? 'delivery.re_released' : 'delivery.reassigned',
            'auditable_type' => Order::class,
            'auditable_id'   => $order->id,
            'meta'           => json_encode([
                'previous_agent_id' => $previousAgentId,
                'new_agent_id'      => $newAgent->id,
                'reason'            => $data['reason'] ?? null,
                'was'               => $status,
            ]),
            'ip'             => request()->ip(),
        ]);

        $label = $isRerelease ? 're-released' : 'reassigned';
        return back()->with('success', "Order {$order->reference} {$label} to {$newAgent->name}.");
    }
}
