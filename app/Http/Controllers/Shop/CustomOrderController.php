<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\CustomOrderColour;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\PickupStation;
use App\Models\Product;
use App\Models\SizeChart;
use App\Notifications\CustomOrderMessageReceived;
use App\Notifications\NotificationRecipients;
use App\Services\CustomFileService;
use App\Services\CustomOrderService;
use App\Services\CustomQuoteService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomOrderController extends Controller
{
    public function __construct(
        private CustomOrderService $customOrderService,
        private CustomFileService $fileService,
        private CustomQuoteService $quoteService,
    ) {}

    public function index(Request $request)
    {
        $filter = $request->input('filter');

        $query = CustomOrder::where('user_id', Auth::id())
            ->with([
                'files' => fn ($q) => $q->where('file_type', 'reference_image')->limit(1),
                // lifecycle_label reads the linked order; eager-load to avoid N+1.
                'order',
            ])
            ->latest();

        if ($filter === 'active') {
            $query->whereNotIn('status', ['completed', 'cancelled', 'rejected']);
        } elseif ($filter === 'completed') {
            $query->where('status', 'completed');
        } elseif ($filter === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'rejected']);
        }

        $orders = $query->paginate(12)->withQueryString();

        return view('shop.custom-frock.index', compact('orders', 'filter'));
    }

    public function create()
    {
        $data = $this->getFormOptions();

        // Check for base product selection
        $baseProduct = null;
        if (request()->has('product_id')) {
            $baseProduct = Product::findOrFail(request('product_id'));
        }

        // Restore session data if available (draft or create-similar)
        $saved = Session::get('custom_order_draft', []);
        Session::forget('custom_order_draft');
        $fileService = $this->fileService;

        // Fresh idempotency key per rendered form; replaying the same submit
        // resolves back to the order it already created.
        $submissionToken = (string) Str::uuid();

        return view('shop.custom-frock.create', array_merge($data, compact('baseProduct', 'saved', 'fileService', 'submissionToken')));
    }

    public function createSimilar(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        // Build pre-population data from the existing order
        $customizations = $customOrder->customizations->pluck('value', 'attribute')->toArray();

        $saved = array_filter([
            'child_name' => $customOrder->child_name,
            'child_age' => $customOrder->child_age,
            'child_gender' => $customOrder->child_gender,
            'category' => $customOrder->category,
            'delivery_method' => $customOrder->delivery_method,
            'pickup_station_id' => $customOrder->pickup_station_id,
            'delivery_location_id' => $customOrder->delivery_location_id,
            'delivery_address' => $customOrder->delivery_address,
            'customer_notes' => null,
            'primary_colour' => $customizations['primary_colour'] ?? null,
            'secondary_colour' => $customizations['secondary_colour'] ?? null,
            'accent_colour' => $customizations['accent_colour'] ?? null,
            'custom_colour_description' => $customOrder->custom_colour_description,
            'standard_size' => $customizations['standard_size'] ?? null,
            'child_size' => $customizations['child_size'] ?? null,
            // Carry the sizing choice forward so the parent can confirm rather than
            // re-enter it. A retired chart is dropped so the form falls back cleanly.
            'size_chart_id' => SizeChart::published()->whereKey($customOrder->size_chart_id)->exists()
                ? $customOrder->size_chart_id
                : null,
            'selected_chart_size' => $customOrder->selected_chart_size,
        ], fn ($v) => $v !== null && $v !== '');

        Session::put('custom_order_draft', $saved);

        return redirect()->route('shop.custom-frock.create')
            ->with('success', 'Previous order details pre-filled. Please review and submit your new custom order.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'submission_token' => ['nullable', 'string', 'max:64'],
            'child_name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'in:'.implode(',', array_keys(CustomOrder::CATEGORIES))],
            'delivery_method' => ['required', 'in:pickup,delivery'],
            'pickup_station_id' => ['required_if:delivery_method,pickup', 'nullable', 'exists:pickup_stations,id'],
            'delivery_location_id' => ['required_if:delivery_method,delivery', 'nullable', 'exists:delivery_locations,id'],
            'delivery_address' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'delivery_location_id' => ['required_if:delivery_method,delivery', 'nullable', 'exists:delivery_locations,id'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'base_product_id' => ['nullable', 'exists:products,id'],
            'primary_colour' => ['required', 'string', 'max:128'],
            'secondary_colour' => ['nullable', 'string', 'max:128'],
            'accent_colour' => ['nullable', 'string', 'max:128'],
            'custom_colour_description' => ['nullable', 'string', 'max:500'],
            'standard_size' => ['nullable', 'string', 'max:64', Rule::requiredIf(function () {
                // Only enforced when no size chart is published, so a chart-backed
                // selection replaces it rather than sitting alongside it.
                return SizeChart::published()->doesntExist();
            })],
            'child_size' => ['nullable', 'string', 'max:64'],
            'sizing_method' => ['nullable', Rule::in([
                CustomOrder::SIZING_STANDARD, CustomOrder::SIZING_MEASUREMENTS,
            ])],
            'size_chart_id' => ['nullable', 'exists:size_charts,id'],
            'selected_chart_size' => ['nullable', 'string', 'max:64', Rule::requiredIf(function () use ($request) {
                // Required only when the submission is actually using a published
                // chart. Without one the form falls back to the age range.
                return (bool) SizeChart::published()->whereKey($request->input('size_chart_id'))->exists();
            })],
            'measurements' => ['nullable', 'array'],
            'measurements.*.value' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'measurements.*.unit' => ['nullable', 'string', 'max:8'],
            'reference_files' => ['nullable', 'array'],
            'reference_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$this->fileService->getMaxFileSizeMb() * 1024],
            'colour_files' => ['nullable', 'array'],
            'colour_files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.$this->fileService->getMaxFileSizeMb() * 1024],
            'return_policy_acknowledged' => ['required', 'accepted'],
        ]);

        $submissionToken = $data['submission_token'] ?? null;

        // Idempotency: the same form submission arriving twice (double click, browser
        // resend, proxy retry) must resolve to the order it already created rather
        // than inserting another one.
        if ($existing = $this->customOrderService->findBySubmissionToken($submissionToken, Auth::id())) {
            return $this->redirectToOrder($existing, duplicate: true);
        }

        if ($data['delivery_method'] === 'delivery'
            && ! DeliveryCharge::activeWithAgentFor((int) ($data['delivery_location_id'] ?? 0))) {
            return back()
                ->withErrors(['delivery_location_id' => DeliveryCharge::UNAVAILABLE_MESSAGE])
                ->withInput();
        }

        $customizations = array_filter([
            'primary_colour' => $data['primary_colour'] ?? null,
            'secondary_colour' => $data['secondary_colour'] ?? null,
            'accent_colour' => $data['accent_colour'] ?? null,
            'standard_size' => $data['standard_size'] ?? null,
            'child_size' => $data['child_size'] ?? null,
        ], fn ($v) => ! empty($v));

        // Resolve the sizing evidence. A chart selection is only honoured from a
        // published chart, so a stale or retired chart id cannot be submitted.
        $sizeChart = null;
        $sizingMethod = $data['sizing_method'] ?? null;
        $selectedSize = $data['selected_chart_size'] ?? null;

        if ($sizeChartId = $data['size_chart_id'] ?? null) {
            $sizeChart = SizeChart::published()->whereKey($sizeChartId)->first();

            if ($sizeChart) {
                $sizingMethod = CustomOrder::SIZING_STANDARD;
                $selectedSize ??= $sizeChart->entries->firstWhere('label', $selectedSize)?->label;
            }
        }

        // Only measurements the parent actually supplied are stored. The array is keyed by
        // measurement type (measurements[chest][value]), so the key is the type.
        $measurements = collect($data['measurements'] ?? [])
            ->map(fn ($row, $type) => [
                'type' => $type,
                'value' => $row['value'] ?? null,
                'unit' => $row['unit'] ?? 'cm',
            ])
            ->filter(fn ($row) => filled($row['value']))
            ->values()
            ->all();

        if ($measurements !== [] && $sizingMethod === null) {
            $sizingMethod = CustomOrder::SIZING_MEASUREMENTS;
        }

        try {
            $order = $this->customOrderService->create([
                'user_id' => Auth::id(),
                'submission_token' => $submissionToken,
                'item_type' => 'frock',
                'category' => $data['category'] ?? null,
                'base_product_id' => $data['base_product_id'] ?? null,
                'child_name' => $data['child_name'],
                'child_gender' => 'girl',
                'sizing_method' => $sizingMethod,
                'size_chart_id' => $sizeChart?->id,
                'size_chart_version' => $sizeChart?->version,
                'selected_chart_size' => $selectedSize,
                'return_policy_acknowledged_at' => now(),
                'delivery_method' => $data['delivery_method'],
                'pickup_station_id' => $data['pickup_station_id'] ?? null,
                'delivery_location_id' => $data['delivery_method'] === 'delivery' ? ($data['delivery_location_id'] ?? null) : null,
                'delivery_fee' => $this->resolveDeliveryFee($data),
                'delivery_address' => $data['delivery_address'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'custom_colour_description' => $data['custom_colour_description'] ?? null,
                'return_policy_acknowledged' => $data['return_policy_acknowledged'] ?? false,
            ], $measurements, $customizations);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent copy of this request won the unique submission_token
            // index; adopt its order instead of failing the customer.
            //
            // Narrowed to unique violations only: any other database error (unknown
            // column, missing table) would previously enter this recovery path and
            // then throw a second, far more confusing error from the lookup below.
            $existing = $this->customOrderService->findBySubmissionToken($submissionToken, Auth::id());

            if (! $existing) {
                throw $e;
            }

            return $this->redirectToOrder($existing, duplicate: true);
        }

        // Handle file uploads
        $this->uploadFiles($order, $data, Auth::id());

        // Submit immediately
        try {
            $this->customOrderService->submit($order);
        } catch (\Throwable $e) {
            Log::error('Custom order submit failed: '.$e->getMessage());
        }

        return $this->redirectToOrder($order);
    }

    public function show(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        $customOrder->load([
            'measurements', 'customizations', 'files', 'quotes',
            'messages.sender', 'messages.file', 'statusHistory.changer',
        ]);

        $latestQuote = $customOrder->latestQuote();
        $approvedQuote = $customOrder->approvedQuote();

        return view('shop.custom-frock.show', compact('customOrder', 'latestQuote', 'approvedQuote'));
    }

    public function approveQuote(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        // Approval and linked-order creation are one atomic unit. Anything that fails
        // leaves the record exactly as it was, so there is never a half-approved quote
        // or an order the customer cannot reach.
        try {
            $linkedOrder = $this->quoteService->approveAndCreateOrder($customOrder, Auth::id());
        } catch (\Throwable $e) {
            Log::error('Custom quote approval failed', [
                'error' => $e->getMessage(),
                'custom_order' => $customOrder->custom_order_number,
                'user' => Auth::id(),
            ]);

            return back()->with(
                'error',
                'We could not complete your approval just now, so nothing was changed and no order was created. Please try again in a moment.'
            );
        }

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
        $admins = NotificationRecipients::adminUsers();
        Notification::send($admins, new CustomOrderMessageReceived($customOrder, $message));

        return back()->with('success', 'Your feedback has been sent. We will revise the quote.');
    }

    public function cancel(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        $this->customOrderService->cancel($customOrder);

        return back()->with('success', 'Your custom order has been cancelled.');
    }

    public function storeMessage(CustomOrder $customOrder, Request $request)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        $data = $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$this->fileService->messageMaxKilobytes()],
        ], [
            'attachment.max' => 'Image must be '.CustomFileService::MESSAGE_MAX_MB.'MB or smaller.',
        ]);

        $file = null;
        if ($request->hasFile('attachment')) {
            $file = $this->fileService->upload($customOrder, $request->file('attachment'), 'message_attachment', Auth::id(), true);
        }

        $text = trim($data['message'] ?? '');

        $customOrder->messages()->create([
            'sender_type' => 'customer',
            'sender_id' => Auth::id(),
            'message' => $text,
            'is_customer_visible' => true,
            'custom_order_file_id' => $file?->id,
            'created_at' => now(),
        ]);

        Notification::send(
            NotificationRecipients::adminUsers(),
            new CustomOrderMessageReceived($customOrder, $text !== '' ? $text : 'Sent an attachment')
        );

        return back()->with('success', 'Message sent.');
    }

    public function confirmInfo(CustomOrder $customOrder)
    {
        abort_unless($customOrder->user_id === Auth::id(), 403);

        if (! CustomOrder::canTransition($customOrder->status, CustomOrder::STATUS_UNDER_REVIEW)) {
            return back()->with('error', 'This order is not waiting for your information.');
        }

        $this->customOrderService->transitionTo($customOrder, CustomOrder::STATUS_UNDER_REVIEW, Auth::id());

        Notification::send(
            NotificationRecipients::adminUsers(),
            new CustomOrderMessageReceived($customOrder, 'The customer has provided the requested information.')
        );

        return back()->with('success', 'Thank you! Your information has been sent to our team.');
    }

    private function redirectToOrder(CustomOrder $order, bool $duplicate = false)
    {
        return redirect()->route('shop.custom-frock.show', $order)
            ->with(
                'success',
                ($duplicate
                    ? 'This request was already submitted. '
                    : 'Your custom frock request has been submitted! ')
                .'Order number: '.$order->custom_order_number
            );
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

    private function resolveDeliveryFee(array $data): float
    {
        if (($data['delivery_method'] ?? null) !== 'delivery' || empty($data['delivery_location_id'])) {
            return 0.0;
        }

        $charge = DeliveryCharge::activeWithAgentFor((int) $data['delivery_location_id']);

        return $charge ? (float) $charge->amount : 0.0;
    }

    private function getFormOptions(): array
    {
        // Only the location ids are needed here, to tell the customer whether delivery
        // is possible at all. The price itself is deliberately not sent to the form:
        // delivery is charged from the quote, so showing a figure before one exists
        // only creates an expectation that may not match what is eventually quoted.
        // resolveDeliveryFee() still records the rate on the order for the admin.
        $deliveryLocationsWithAgent = DeliveryCharge::where('is_active', true)
            ->whereHas('agent', fn ($q) => $q->where('is_active', true))
            ->pluck('delivery_location_id')
            ->values();

        return [
            'colours' => CustomOrderColour::active()->get(),
            'pickupStations' => PickupStation::where('is_active', true)->where('is_available', true)->orderBy('name')->get(),
            'deliveryLocations' => DeliveryLocation::where('is_active', true)->orderBy('name')->get(),
            'deliveryCharges' => $deliveryLocationsWithAgent,

            // Only published charts may be offered. A chart with no measurements can
            // never be published, so the selector is never populated with blank
            // options or invented figures.
            'sizeChart' => SizeChart::published()
                ->with(['entries' => fn ($q) => $q->orderBy('sort_order'), 'entries.measurements'])
                ->orderByDesc('version')
                ->first(),
            'measurementFields' => \App\Models\CustomOrderMeasurementField::active()->get(),
            'bodyGuides' => \App\Models\CustomOrderMeasurementGuide::active()
                ->where('basis', 'body')->get()->keyBy('measurement_type'),
        ];
    }
}
