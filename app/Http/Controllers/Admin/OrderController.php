<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\PaymentRequest;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private PaymentService $payments
    ) {
    }

    public function index(): View
    {
        $orders = Order::with('customer')->latest()->get();

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

        return back()->with('success', 'Order cancelled.');
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

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
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
        ];

        $method = $methodMap[$newStatus] ?? null;

        if ($method && method_exists($this->orders, $method)) {
            $this->orders->{$method}($order);
        } else {
            $order->update(['status' => $newStatus]);
        }

        return back()->with('success', 'Order status updated to ' . ucfirst($newStatus) . '.');
    }
}
