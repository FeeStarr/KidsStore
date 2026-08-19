<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\CustomOrderColour;
use App\Models\PickupStation;
use App\Models\Product;
use App\Models\User;
use App\Notifications\CustomOrderMessageReceived;
use App\Services\CustomFileService;
use App\Services\CustomOrderService;
use App\Services\CustomPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;

class CustomOrderController extends Controller
{
    public function __construct(
        private CustomOrderService $customOrderService,
        private CustomFileService $fileService,
        private CustomPaymentService $paymentService,
    ) {}

    public function index()
    {
        $orders = CustomOrder::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('shop.custom-frock.index', compact('orders'));
    }

    public function create()
    {
        $data = $this->getFormOptions();

        // Check for base product selection
        $baseProduct = null;
        if (request()->has('product_id')) {
            $baseProduct = Product::findOrFail(request('product_id'));
        }

        // Restore session data if available
        $saved = Session::get('custom_order_draft', []);
        $fileService = $this->fileService;

        return view('shop.custom-frock.create', array_merge($data, compact('baseProduct', 'saved', 'fileService')));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'child_name' => ['required', 'string', 'max:100'],
            'delivery_method' => ['required', 'in:pickup,delivery'],
            'pickup_station_id' => ['required_if:delivery_method,pickup', 'nullable', 'exists:pickup_stations,id'],
            'delivery_address' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'base_product_id' => ['nullable', 'exists:products,id'],
            'primary_colour' => ['required', 'string', 'max:128'],
            'secondary_colour' => ['nullable', 'string', 'max:128'],
            'accent_colour' => ['nullable', 'string', 'max:128'],
            'custom_colour_description' => ['nullable', 'string', 'max:500'],
            'standard_size' => ['required', 'string', 'max:64'],
            'child_size' => ['nullable', 'string', 'max:64'],
            'reference_files' => ['nullable', 'array'],
            'reference_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$this->fileService->getMaxFileSizeMb() * 1024],
            'colour_files' => ['nullable', 'array'],
            'colour_files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.$this->fileService->getMaxFileSizeMb() * 1024],
            'return_policy_acknowledged' => ['required', 'accepted'],
        ]);

        $customizations = array_filter([
            'primary_colour' => $data['primary_colour'] ?? null,
            'secondary_colour' => $data['secondary_colour'] ?? null,
            'accent_colour' => $data['accent_colour'] ?? null,
            'standard_size' => $data['standard_size'] ?? null,
            'child_size' => $data['child_size'] ?? null,
        ], fn ($v) => ! empty($v));

        $order = $this->customOrderService->create([
            'user_id' => Auth::id(),
            'item_type' => 'frock',
            'base_product_id' => $data['base_product_id'] ?? null,
            'child_name' => $data['child_name'],
            'child_gender' => 'girl',
            'delivery_method' => $data['delivery_method'],
            'pickup_station_id' => $data['pickup_station_id'] ?? null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'custom_colour_description' => $data['custom_colour_description'] ?? null,
            'return_policy_acknowledged' => $data['return_policy_acknowledged'] ?? false,
        ], [], $customizations);

        // Handle file uploads
        $this->uploadFiles($order, $data, Auth::id());

        // Submit immediately
        try {
            $this->customOrderService->submit($order);
        } catch (\Throwable $e) {
            Log::error('Custom order submit failed: '.$e->getMessage());
        }

        return redirect()->route('shop.custom-frock.show', $order)
            ->with('success', 'Your custom frock request has been submitted! Order number: '.$order->custom_order_number);
    }

    public function show(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        $customOrder->load([
            'measurements', 'customizations', 'files', 'quotes',
            'messages.sender', 'statusHistory.changer',
        ]);

        $latestQuote = $customOrder->latestQuote();
        $approvedQuote = $customOrder->approvedQuote();

        return view('shop.custom-frock.show', compact('customOrder', 'latestQuote', 'approvedQuote'));
    }

    public function approveQuote(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        $quote = $customOrder->approvedQuote() ?? $customOrder->latestQuote();

        if (! $quote || $quote->isExpired()) {
            return back()->with('error', 'This quote has expired. Please request a new one.');
        }

        // Approve the quote
        $this->customOrderService->transitionTo(
            $customOrder,
            CustomOrder::STATUS_CUSTOMER_APPROVED,
            Auth::id()
        );

        // Create linked order for payment processing
        $linkedOrder = $this->paymentService->createLinkedOrder($customOrder);

        return redirect()->route('shop.account.orders.show', $linkedOrder)
            ->with('success', 'Quote approved! Please proceed to payment.');
    }

    public function payment(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        // Redirect to the linked order's payment page
        $linkedOrder = $customOrder->order;
        if (! $linkedOrder) {
            return redirect()->route('shop.custom-frock.show', $customOrder)
                ->with('error', 'No payment record found. Please contact support.');
        }

        return redirect()->route('shop.account.orders.show', $linkedOrder);
    }

    public function requestChanges(CustomOrder $customOrder, Request $request)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $this->customOrderService->transitionTo(
            $customOrder,
            CustomOrder::STATUS_NEEDS_REVISION,
            Auth::id()
        );

        $message = $request->input('message');

        $customOrder->messages()->create([
            'sender_type' => 'customer',
            'sender_id' => Auth::id(),
            'message' => $message,
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        // Notify admins
        $admins = User::role(['superadmin', 'admin'])->get();
        Notification::send($admins, new CustomOrderMessageReceived($customOrder, $message));

        return back()->with('success', 'Your feedback has been sent. We will revise the quote.');
    }

    public function cancel(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        $this->customOrderService->cancel($customOrder);

        return back()->with('success', 'Your custom order has been cancelled.');
    }

    private function uploadFiles(CustomOrder $order, array $data, int $userId): void
    {
        if (! empty($data['reference_files'])) {
            foreach ($data['reference_files'] as $file) {
                if ($file->isValid()) {
                    $this->fileService->upload($order, $file, 'reference_image', $userId);
                }
            }
        }

        if (! empty($data['colour_files'])) {
            foreach ($data['colour_files'] as $file) {
                if ($file->isValid()) {
                    $this->fileService->upload($order, $file, 'colour_reference', $userId);
                }
            }
        }
    }

    private function getFormOptions(): array
    {
        return [
            'colours' => CustomOrderColour::active()->get(),
            'pickupStations' => PickupStation::where('is_active', true)->where('is_available', true)->orderBy('name')->get(),
        ];
    }
}
