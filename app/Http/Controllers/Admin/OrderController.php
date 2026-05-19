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
        $order->load('items.product', 'items.variant', 'payments', 'customer');

        return view('admin.orders.show', compact('order'));
    }

    public function confirm(Order $order): RedirectResponse
    {
        $this->orders->confirm($order);

        return back()->with('success', 'Order confirmed. Inventory updated.');
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
}
