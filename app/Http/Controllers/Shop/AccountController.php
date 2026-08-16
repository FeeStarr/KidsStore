<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\View\View;

class AccountController extends Controller
{
    public function orders(): View
    {
        $orders = Order::with('items.product')
            ->where('customer_id', Auth::id())
            ->latest()
            ->limit(500)
            ->get();

        return view('shop.account.orders', compact('orders'));
    }

    public function showOrder(Order $order): View
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 404);

        $order->load(['items.product.primaryImage', 'items.variant', 'payments', 'pickupStation', 'paymentTransactions', 'refundRequests']);

        return view('shop.account.order-show', compact('order'));
    }

    public function profile(): View
    {
        $user = Auth::user();
        $user->load(['profile', 'addresses']);

        return view('shop.account.profile', compact('user'));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_url' => ['nullable', 'url', 'max:255'],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'bio' => $data['bio'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Profile updated.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $user->addresses()->create([
            'label' => $data['label'] ?? null,
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Nigeria',
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);

        return redirect()->back()->with('success', 'Address added.');
    }

    public function updateAddress(Request $request, Address $address): RedirectResponse
    {
        $user = Auth::user();
        abort_unless((int) $address->user_id === (int) $user->id, 404);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address->update([
            'label' => $data['label'] ?? null,
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Nigeria',
            'is_default' => (bool) ($data['is_default'] ?? $address->is_default),
        ]);

        return redirect()->back()->with('success', 'Address updated.');
    }

    public function destroyAddress(Address $address): RedirectResponse
    {
        $user = Auth::user();
        abort_unless((int) $address->user_id === (int) $user->id, 404);

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = Address::where('user_id', $user->id)->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return redirect()->back()->with('success', 'Address removed.');
    }

    public function setDefaultAddress(Address $address): RedirectResponse
    {
        $user = Auth::user();
        abort_unless((int) $address->user_id === (int) $user->id, 404);

        Address::where('user_id', $user->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->back()->with('success', 'Default address updated.');
    }

    public function changePaymentMethod(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 404);

        if ($order->payment_status === 'paid') {
            return back()->with('error', 'Cannot change payment method for a paid order.');
        }

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'exists:payment_methods,key'],
        ]);

        $order->update(['payment_method' => $data['payment_method']]);

        // If switching to pay_at_pickup on a pending payment order, confirm it immediately
        if ($data['payment_method'] === 'pay_at_pickup' && $order->status === 'pending payment') {
            app(OrderService::class)->confirm($order);
        }

        return back()->with('success', 'Payment method updated to ' . ucfirst(str_replace('_', ' ', $data['payment_method'])) . '.');
    }
}
