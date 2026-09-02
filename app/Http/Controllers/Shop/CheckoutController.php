<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PickupStation;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\GuestOtpService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private OrderService $orders,
        private GuestOtpService $otpService,
    ) {}

    /**
     * Step 1 for guests: verify email via OTP.
     * Logged-in users skip straight to checkout.
     */
    public function show()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        // Logged-in users go straight to checkout
        if (Auth::check()) {
            if (! Auth::user()->hasVerifiedEmail()) {
                return redirect()->route('shop.account.profile')
                    ->with('error', 'Please verify your email before checking out.');
            }
            return $this->showCheckout();
        }

        // Check if guest email is already verified via OTP
        $guestEmail = session('guest_checkout_email');
        if ($guestEmail && $this->otpService->isVerified($guestEmail)) {
            return $this->showCheckout();
        }

        // Show OTP verification step
        return view('shop.checkout.verify-email');
    }

    /**
     * Guest: send OTP to their email.
     */
    public function sendOtp(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));

        $sent = $this->otpService->sendOtp($email);

        if (! $sent) {
            return back()->with('error', 'Please wait a moment before requesting another code.');
        }

        session(['guest_checkout_email' => $email]);

        return redirect()->route('shop.checkout.verify-otp')
            ->with('email', $email)
            ->with('success', 'A 6-digit verification code has been sent to ' . $email);
    }

    /**
     * Guest: show OTP entry form.
     */
    public function showVerifyOtp()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $email = session('guest_checkout_email') ?? session('email');

        return view('shop.checkout.verify-otp', ['email' => $email]);
    }

    /**
     * Guest: verify the OTP code.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower(trim($data['email']));
        $code  = trim($data['code']);

        $verified = $this->otpService->verify($email, $code);

        if (! $verified) {
            return back()->with('error', 'Invalid or expired verification code. Please try again.');
        }

        session(['guest_checkout_email' => $email]);

        return redirect()->route('shop.checkout.show')
            ->with('success', 'Email verified! Please complete your order.');
    }

    /**
     * Guest: resend OTP.
     */
    public function resendOtp(): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $email = session('guest_checkout_email');

        if (! $email) {
            return redirect()->route('shop.checkout.show');
        }

        $sent = $this->otpService->sendOtp($email);

        if (! $sent) {
            return back()->with('error', 'Please wait a moment before requesting another code.');
        }

        return back()->with('success', 'A new verification code has been sent to ' . $email);
    }

    /**
     * Show the actual checkout form (after OTP verification for guests).
     */
    private function showCheckout()
    {
        $pickupStations = PickupStation::where('is_active', true)->where('is_available', true)->orderBy('name')->get();
        $coupon = $this->cart->coupon();
        $customer = Auth::user();
        $guestEmail = session('guest_checkout_email');

        return view('shop.checkout.show', [
            'items'           => $this->cart->items(),
            'subtotal'        => $this->cart->subtotal(),
            'coupon'          => $coupon,
            'coupon_discount' => $coupon ? $this->cart->couponDiscount() : 0.0,
            'customer'        => $customer,
            'guestEmail'      => $guestEmail,
            'pickupStations'  => $pickupStations,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $isGuest = ! Auth::check();

        // Logged-in users must have verified email
        if (! $isGuest && ! Auth::user()->hasVerifiedEmail()) {
            return redirect()->route('shop.account.profile')
                ->with('error', 'Please verify your email before checking out.');
        }

        $rules = [
            'delivery_method'    => ['required', 'in:delivery,pickup'],
            'phone'              => ['required', 'string', 'max:30'],
            'address'            => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'pickup_station_id'  => ['required_if:delivery_method,pickup', 'nullable', 'exists:pickup_stations,id'],
            'note'               => ['nullable', 'string', 'max:500'],
            'payment_method'     => ['required', 'in:pay_now,pay_on_delivery'],
        ];

        if ($isGuest) {
            $rules['name']  = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        $data = $request->validate($rules);

        // Guest OTP verification check
        if ($isGuest) {
            $email = strtolower(trim($data['email']));
            if (! $this->otpService->isVerified($email)) {
                return back()->with('error', 'Please verify your email before placing an order.')
                    ->withInput();
            }
        }

        $customer = Auth::user();
        $customerId = null;
        $guestName = null;
        $guestEmail = null;

        if ($customer) {
            $customer->fill(['phone' => $data['phone']])->save();
            $customerId = $customer->id;
        } else {
            $guestName = $data['name'];
            $guestEmail = $data['email'];
        }

        $items = $this->cart->items()->map(fn ($l) => [
            'product_id'           => $l->product->id,
            'product_variant_id'   => $l->variant->id,
            'quantity'             => $l->quantity,
            'unit_price'           => $l->unit_price,
            'original_unit_price'  => $l->original_unit_price,
            'discount'             => $l->discount,
            'discount_amount'      => $l->discount_amount,
            'deal_id'              => $l->deal_id,
        ])->all();

        $shippingFee = 0;
        $shippingFeeSetting = Setting::get('shipping_fee', null);
        if ($shippingFeeSetting !== null && $shippingFeeSetting !== '') {
            $shippingFee = (float) $shippingFeeSetting;
        } else {
            $shippingFee = (float) ($data['shipping_fee'] ?? 0);
        }

        // Pay Now → pending payment. Pay on Delivery → pending confirmation.
        $orderStatus = $data['payment_method'] === 'pay_now'
            ? 'pending payment'
            : 'pending confirmation';

        try {
            $order = $this->orders->create([
                'customer_id'       => $customerId,
                'guest_name'        => $guestName,
                'guest_email'       => $guestEmail,
                'guest_phone'       => $data['phone'],
                'lookup_token'      => Str::random(64),
                'order_date'        => now()->toDateString(),
                'status'            => $orderStatus,
                'delivery_method'   => $data['delivery_method'],
                'payment_method'    => $data['payment_method'],
                'pickup_station_id' => $data['delivery_method'] === 'pickup' ? ($data['pickup_station_id'] ?? null) : null,
                'delivery_address'  => $data['delivery_method'] === 'delivery' ? ($data['address'] ?? null) : null,
                'shipping_fee'      => $shippingFee,
                'note'              => trim($data['note'] ?? '') ?: null,
                'coupon_id'         => $this->cart->couponId(),
                'items'             => $items,
            ]);

            $this->cart->clear();
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'Could not place order. Please try again.');
        }

        // Pay Now → redirect to order page with Paystack popup
        if ($data['payment_method'] === 'pay_now') {
            if ($isGuest) {
                return redirect()->route('shop.order.track', ['token' => $order->lookup_token])
                    ->with('success', 'Order placed! Order Number: ' . $order->reference)
                    ->with('show_pay_now', true);
            }

            return redirect()->route('shop.account.orders.show', $order)
                ->with('success', 'Order placed! Order Number: ' . $order->reference)
                ->with('show_pay_now', true);
        }

        // Pay on Delivery → order pending confirmation, no payment needed now
        if ($isGuest) {
            return redirect()->route('shop.order.track', ['token' => $order->lookup_token])
                ->with('success', 'Order placed! Order Number: ' . $order->reference . '. Your order is pending confirmation.')
                ->with('info', 'We\'ll review your order and confirm it shortly. You\'ll receive an email once confirmed.');
        }

        return redirect()->route('shop.account.orders.show', $order)
            ->with('success', 'Order placed! Order Number: ' . $order->reference)
            ->with('info', 'Your order is pending confirmation. We\'ll notify you once it\'s approved.');
    }

    public function orderLookupForm()
    {
        return view('shop.checkout.lookup');
    }

    public function orderLookup(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:30'],
            'email'     => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $reference = trim($data['reference']);

        $order = Order::where('reference', $reference)
            ->where(function ($q) use ($email) {
                $q->whereRaw('LOWER(guest_email) = ?', [$email])
                  ->orWhereHas('customer', fn ($q2) => $q2->whereRaw('LOWER(email) = ?', [$email]));
            })
            ->first();

        if (! $order) {
            return back()->with('error', 'No order found with that reference and email combination.');
        }

        // Backfill missing lookup_token for legacy orders so Track page works for everyone
        if (! $order->lookup_token) {
            $order->update(['lookup_token' => Str::random(64)]);
            $order->refresh();
        }

        // Logged-in owners bypass OTP
        if (Auth::check() && (int) $order->customer_id === (int) Auth::id()) {
            session(['track_verified_' . $order->lookup_token => true]);
            return redirect()->route('shop.order.track', $order->lookup_token);
        }

        // Already verified this token in this session/cache (15 min)
        if ($this->isTrackVerified($order->lookup_token)) {
            return redirect()->route('shop.order.track', $order->lookup_token);
        }

        $sent = $this->otpService->sendOtp($email);
        if (! $sent) {
            return back()->with('error', 'Please wait a moment before requesting another code.');
        }

        session(['track_pending_token' => $order->lookup_token, 'track_pending_email' => $email]);

        return redirect()->route('shop.order.track.verify', $order->lookup_token)
            ->with('success', 'A 6-digit verification code has been sent to ' . $email);
    }

    public function showTrackOtp(string $token)
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();

        // Owners bypass
        if (Auth::check() && (int) $order->customer_id === (int) Auth::id()) {
            session(['track_verified_' . $token => true]);
            return redirect()->route('shop.order.track', $token);
        }

        if ($this->isTrackVerified($token)) {
            return redirect()->route('shop.order.track', $token);
        }

        $email = session('track_pending_email');

        // Fallback to order email if session lost
        if (! $email) {
            $email = $order->guest_email ?? $order->customer?->email ?? '';
        }

        return view('shop.checkout.track-verify', ['order' => $order, 'email' => $email, 'token' => $token]);
    }

    public function verifyTrackOtp(Request $request, string $token)
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
            'code'  => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower(trim($data['email']));
        $code  = trim($data['code']);

        // Verify email belongs to this order
        $orderEmail = strtolower($order->guest_email ?? $order->customer?->email ?? '');
        $isOwnerEmail = $orderEmail === $email || $this->otpService->isVerified($email);

        if (! $isOwnerEmail) {
            // Still allow if GuestOtp verification passes for the provided email,
            // but ensure the order email matches or customer email matches via LOWER check
            $matchesOrder = Order::where('id', $order->id)
                ->where(function ($q) use ($email) {
                    $q->whereRaw('LOWER(guest_email) = ?', [$email])
                      ->orWhereHas('customer', fn ($q2) => $q2->whereRaw('LOWER(email) = ?', [$email]));
                })->exists();
            if (! $matchesOrder) {
                return back()->with('error', 'Email does not match this order.');
            }
        }

        $verified = $this->otpService->verify($email, $code);
        if (! $verified) {
            return back()->with('error', 'Invalid or expired verification code. Please try again.');
        }

        $this->markTrackVerified($token);
        session()->forget(['track_pending_token', 'track_pending_email']);

        return redirect()->route('shop.order.track', $token)
            ->with('success', 'Email verified. Showing your order.');
    }

    public function resendTrackOtp(string $token)
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();
        $email = session('track_pending_email') ?? $order->guest_email ?? $order->customer?->email ?? null;

        if (! $email) {
            return redirect()->route('shop.order.lookup')->with('error', 'Please search for your order again.');
        }

        $sent = $this->otpService->sendOtp(strtolower($email));
        if (! $sent) {
            return back()->with('error', 'Please wait a moment before requesting another code.');
        }

        return back()->with('success', 'A new verification code has been sent to ' . $email);
    }

    public function orderTrack(string $token)
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();

        // Authenticated owner bypass
        if (Auth::check() && (int) $order->customer_id === (int) Auth::id()) {
            return view('shop.checkout.track', ['order' => $order->load(['items.product', 'items.variant', 'pickupStation'])]);
        }

        if (! $this->isTrackVerified($token)) {
            // Store pending for resend
            $email = $order->guest_email ?? $order->customer?->email ?? '';
            if ($email) {
                session(['track_pending_token' => $token, 'track_pending_email' => strtolower($email)]);
            }
            return redirect()->route('shop.order.track.verify', $token)
                ->with('error', 'Please verify your email to view this order.');
        }

        return view('shop.checkout.track', ['order' => $order->load(['items.product', 'items.variant', 'pickupStation'])]);
    }

    private function isTrackVerified(string $token): bool
    {
        if (session('track_verified_' . $token)) {
            return true;
        }
        return (bool) \Illuminate\Support\Facades\Cache::get('track_verified_' . $token);
    }

    private function markTrackVerified(string $token): void
    {
        session(['track_verified_' . $token => true]);
        \Illuminate\Support\Facades\Cache::put('track_verified_' . $token, true, 900); // 15 min
    }
}
