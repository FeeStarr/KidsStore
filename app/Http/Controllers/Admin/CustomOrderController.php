<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\CustomOrderColour;
use App\Models\CustomOrderEmbellishment;
use App\Models\CustomOrderFabric;
use App\Models\CustomOrderLength;
use App\Models\CustomOrderMeasurementField;
use App\Models\CustomOrderMeasurementGuide;
use App\Models\CustomOrderNeckline;
use App\Models\CustomOrderQcCheck;
use App\Models\CustomOrderSleeve;
use App\Models\CustomOrderSkirt;
use App\Models\CustomOrderStyle;
use App\Models\CustomOrderWaist;
use App\Services\CustomFileService;
use App\Services\CustomOrderService;
use App\Services\CustomQuoteService;
use App\Notifications\CustomOrderMessageReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomOrderController extends Controller
{
    public function __construct(
        private CustomOrderService $customOrderService,
        private CustomQuoteService $quoteService,
        private CustomFileService $fileService,
    ) {}

    public function index(Request $request)
    {
        $query = CustomOrder::with(['user', 'quotes', 'order']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('custom_order_number', 'like', "%{$search}%")
                  ->orWhere('child_name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $orders = $query->latest()->paginate(20);
        $stats = [
            'total' => CustomOrder::count(),
            'pending' => CustomOrder::whereIn('status', ['submitted', 'under_review'])->count(),
            'quoted' => CustomOrder::where('status', 'quoted')->count(),
            'in_production' => CustomOrder::whereIn('status', ['paid', 'production_pending', 'in_production'])->count(),
        ];

        return view('admin.custom-orders.index', compact('orders', 'stats'));
    }

    public function show(CustomOrder $customOrder)
    {
        $customOrder->load([
            'user', 'baseProduct', 'pickupStation',
            'measurements', 'customizations', 'files',
            'quotes.creator', 'messages.sender', 'statusHistory.changer',
            'qcChecks.checker', 'order',
        ]);

        $latestQuote = $customOrder->latestQuote();
        $approvedQuote = $customOrder->approvedQuote();

        return view('admin.custom-orders.show', compact('customOrder', 'latestQuote', 'approvedQuote'));
    }

    public function review(CustomOrder $customOrder)
    {
        $this->customOrderService->review($customOrder, Auth::id());
        return back()->with('success', 'Order marked as under review.');
    }

    public function requestInfo(CustomOrder $customOrder, Request $request)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $this->customOrderService->requestInfo($customOrder, Auth::id());

        $customOrder->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => Auth::id(),
            'message' => $request->input('message'),
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Information request sent to customer.');
    }

    public function approveForQuote(CustomOrder $customOrder)
    {
        $this->customOrderService->approveForQuote($customOrder, Auth::id());
        return back()->with('success', 'Order approved for quotation.');
    }

    public function reject(CustomOrder $customOrder, Request $request)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $this->customOrderService->reject($customOrder, $request->input('reason'), Auth::id());
        return back()->with('success', 'Order rejected.');
    }

    public function storeQuote(CustomOrder $customOrder, Request $request)
    {
        $data = $request->validate([
            'base_price' => 'required|numeric|min:0',
            'fabric_cost' => 'required|numeric|min:0',
            'customization_cost' => 'required|numeric|min:0',
            'embellishment_cost' => 'required|numeric|min:0',
            'measurement_fee' => 'required|numeric|min:0',
            'rush_fee' => 'required|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'valid_days' => 'required|integer|min:1|max:90',
        ]);

        $this->quoteService->create(
            $customOrder,
            $data,
            $data['total'],
            $data['notes'] ?? null,
            $data['valid_days'],
            Auth::id()
        );

        return back()->with('success', 'Quote created and sent to customer.');
    }

    public function startProduction(CustomOrder $customOrder)
    {
        $this->customOrderService->startProduction($customOrder, Auth::id());
        return back()->with('success', 'Production started.');
    }

    public function submitForQc(CustomOrder $customOrder)
    {
        $this->customOrderService->submitForQc($customOrder, Auth::id());
        return back()->with('success', 'Submitted for quality check.');
    }

    public function qualityCheck(CustomOrder $customOrder, Request $request)
    {
        $data = $request->validate([
            'passed' => 'required|boolean',
        ]);

        if ($data['passed']) {
            $this->customOrderService->passQc($customOrder, $customOrder->delivery_method, Auth::id());
            return back()->with('success', 'QC passed.');
        } else {
            $this->customOrderService->failQc($customOrder, Auth::id());
            return back()->with('warning', 'QC failed. Returned to production.');
        }
    }

    public function updateQcCheck(CustomOrder $customOrder, CustomOrderQcCheck $check, Request $request)
    {
        abort_unless($check->custom_order_id === $customOrder->id, 404);

        $data = $request->validate([
            'passed' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $check->update([
            'passed' => $data['passed'],
            'notes' => $data['notes'] ?? null,
            'checked_by' => Auth::id(),
            'checked_at' => now(),
        ]);

        return back()->with('success', 'QC check updated.');
    }

    public function markReady(CustomOrder $customOrder)
    {
        if ($customOrder->delivery_method === 'pickup') {
            $this->customOrderService->markReadyForPickup($customOrder, Auth::id());
        } else {
            $this->customOrderService->transitionTo($customOrder, CustomOrder::STATUS_READY_FOR_DELIVERY, Auth::id());
        }
        return back()->with('success', 'Order marked as ready.');
    }

    public function markShipped(CustomOrder $customOrder)
    {
        $this->customOrderService->markShipped($customOrder, Auth::id());
        return back()->with('success', 'Order shipped.');
    }

    public function complete(CustomOrder $customOrder)
    {
        $this->customOrderService->complete($customOrder, Auth::id());
        return back()->with('success', 'Order completed.');
    }

    public function sendMessage(CustomOrder $customOrder, Request $request)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $message = $request->input('message');

        $customOrder->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => Auth::id(),
            'message' => $message,
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        $customOrder->user->notify(new CustomOrderMessageReceived($customOrder, $message));

        return back()->with('success', 'Message sent.');
    }

    public function updateNotes(CustomOrder $customOrder, Request $request)
    {
        $request->validate(['admin_notes' => 'nullable|string|max:2000']);
        $customOrder->update(['admin_notes' => $request->input('admin_notes')]);
        return back()->with('success', 'Notes updated.');
    }

    public function serveFile(CustomOrder $customOrder, \App\Models\CustomOrderFile $file)
    {
        abort_unless($file->custom_order_id === $customOrder->id, 404);

        $response = $this->fileService->serve($file);
        if (!$response) abort(404);
        return $response;
    }
}
