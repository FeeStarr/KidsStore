<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use App\Models\CustomOrderFile;
use App\Models\CustomOrderQcCheck;
use App\Notifications\CustomOrderMessageReceived;
use App\Services\CustomFileService;
use App\Services\CustomOrderService;
use App\Services\CustomQuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomOrderController extends Controller
{
    /**
     * The administrator's id.
     *
     * The admin panel has its own session guard, so the default guard is the shop's
     * and holds nobody in an admin request. Reading Auth::id() here returns null,
     * which left every message with a null sender and every status change with no
     * recorded actor. Note that actingAs($user, 'admin') in a test switches the
     * default guard too, so a test can pass while production does not.
     */
    private function adminId(): ?int
    {
        return Auth::guard('admin')->id();
    }

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

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('custom_order_number', 'like', "%{$search}%")
                    ->orWhere('child_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
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
            'measurements', 'comparisonMeasurements', 'customizations', 'files',
            'quotes.creator', 'messages.sender', 'messages.file', 'statusHistory.changer',
            'qcChecks.checker', 'order', 'customCreation',
        ]);

        $latestQuote = $customOrder->latestQuote();
        $approvedQuote = $customOrder->approvedQuote();

        return view('admin.custom-orders.show', compact('customOrder', 'latestQuote', 'approvedQuote'));
    }

    public function review(CustomOrder $customOrder)
    {
        $this->customOrderService->review($customOrder, $this->adminId());

        return back()->with('success', 'Order marked as under review.');
    }

    public function requestInfo(CustomOrder $customOrder, Request $request)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        if (! CustomOrder::canTransition($customOrder->status, CustomOrder::STATUS_NEEDS_INFORMATION)) {
            return back()->with('error', 'This order is no longer waiting for review.');
        }

        $this->customOrderService->requestInfo($customOrder, $request->input('message'), $this->adminId());

        $customOrder->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => $this->adminId(),
            'message' => $request->input('message'),
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Information request sent to customer.');
    }

    public function approveForQuote(CustomOrder $customOrder)
    {
        $this->customOrderService->approveForQuote($customOrder, $this->adminId());

        return back()->with('success', 'Order approved for quotation.');
    }

    public function reject(CustomOrder $customOrder, Request $request)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $this->customOrderService->reject($customOrder, $request->input('reason'), $this->adminId());

        return back()->with('success', 'Order rejected.');
    }

    public function storeQuote(CustomOrder $customOrder, Request $request)
    {
        if (! CustomOrder::canTransition($customOrder->status, CustomOrder::STATUS_QUOTED)) {
            return back()->with('error', 'This order is not ready for a quote.');
        }

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
            $this->adminId()
        );

        return back()->with('success', 'Quote created and sent to customer.');
    }

    public function cancel(CustomOrder $customOrder, Request $request)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->customOrderService->cancelAsAdmin(
                $customOrder,
                trim($data['reason']),
                Auth::guard('admin')->id()
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Request cancelled.');
    }

    public function startProduction(CustomOrder $customOrder)
    {
        try {
            $this->customOrderService->startProduction($customOrder, $this->adminId());
        } catch (\App\Exceptions\ProductionGateException $e) {
            // The service is the authority on whether an order may be produced.
            // There is no way round this from the admin interface: the missing
            // evidence has to be resolved with the customer.
            return back()->with('error', 'This order cannot start production yet. '.implode(' ', $e->blockers()));
        }

        return back()->with('success', 'Production started.');
    }

    public function submitForQc(CustomOrder $customOrder)
    {
        $this->customOrderService->submitForQc($customOrder, $this->adminId());

        return back()->with('success', 'Submitted for quality check.');
    }

    public function resumeProduction(CustomOrder $customOrder)
    {
        $this->customOrderService->resumeProduction($customOrder, $this->adminId());

        return back()->with('success', 'Production resumed.');
    }

    public function qualityCheck(CustomOrder $customOrder, Request $request)
    {
        $data = $request->validate([
            'passed' => 'required|boolean',
        ]);

        if ($data['passed']) {
            $this->customOrderService->passQc($customOrder, $customOrder->delivery_method, $this->adminId());

            return back()->with('success', 'QC passed.');
        } else {
            $this->customOrderService->failQc($customOrder, $this->adminId());

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
            'checked_by' => $this->adminId(),
            'checked_at' => now(),
        ]);

        return back()->with('success', 'QC check updated.');
    }

    public function attachEvidence(CustomOrder $customOrder, CustomOrderQcCheck $check, Request $request)
    {
        abort_unless($check->custom_order_id === $customOrder->id, 404);

        $data = $request->validate([
            'notes' => 'nullable|string|max:500|required_without:photo',
            'photo' => 'nullable|image|max:5120|required_without:notes',
        ]);

        if ($request->has('notes')) {
            $check->notes = trim($data['notes'] ?? '') !== '' ? $data['notes'] : null;
        }

        if ($request->hasFile('photo')) {
            $check->photo_path = $this->fileService->storeQcPhoto($customOrder, $check, $request->file('photo'));
        }

        $check->save();

        return back()->with('success', 'QC evidence saved.');
    }

    public function serveQcPhoto(CustomOrder $customOrder, CustomOrderQcCheck $check)
    {
        abort_unless($check->custom_order_id === $customOrder->id, 404);

        $response = $this->fileService->serveQcPhoto($customOrder, $check);
        if (! $response) {
            abort(404);
        }

        return $response;
    }

    public function markReady(CustomOrder $customOrder)
    {
        if ($customOrder->delivery_method === 'pickup') {
            $this->customOrderService->markReadyForPickup($customOrder, $this->adminId());
        } else {
            $this->customOrderService->markReadyForDelivery($customOrder, $this->adminId());
        }

        return back()->with('success', 'Order marked as ready.');
    }

    public function markShipped(CustomOrder $customOrder)
    {
        $this->customOrderService->markShipped($customOrder, $this->adminId());

        return back()->with('success', 'Order shipped.');
    }

    public function complete(CustomOrder $customOrder)
    {
        $this->customOrderService->complete($customOrder, $this->adminId());

        return back()->with('success', 'Order completed.');
    }

    public function sendMessage(CustomOrder $customOrder, Request $request)
    {
        $request->validate([
            'message' => ['required_without:attachment', 'nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$this->fileService->messageMaxKilobytes()],
        ], [
            'attachment.max' => 'Image must be '.CustomFileService::MESSAGE_MAX_MB.'MB or smaller.',
        ]);

        $text = trim($request->input('message', ''));

        $file = $request->hasFile('attachment')
            ? $this->fileService->upload($customOrder, $request->file('attachment'), 'message_attachment', $this->adminId(), true)
            : null;

        $customOrder->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => $this->adminId(),
            'message' => $text,
            'is_customer_visible' => true,
            'custom_order_file_id' => $file?->id,
            'created_at' => now(),
        ]);

        // The message is saved. Whether the email about it goes out is a separate
        // matter, and must not turn a sent message into an error page - the admin
        // would only send it again.
        $this->customOrderService->sendSafely(
            $customOrder,
            new CustomOrderMessageReceived($customOrder, $text !== '' ? $text : 'Sent an attachment')
        );

        return back()->with('success', 'Message sent.');
    }

    public function updateNotes(CustomOrder $customOrder, Request $request)
    {
        $request->validate(['admin_notes' => 'nullable|string|max:2000']);
        $customOrder->update(['admin_notes' => $request->input('admin_notes')]);

        return back()->with('success', 'Notes updated.');
    }

    public function serveFile(CustomOrder $customOrder, CustomOrderFile $file)
    {
        abort_unless($file->custom_order_id === $customOrder->id, 404);

        $response = $this->fileService->serve($file);
        if (! $response) {
            abort(404);
        }

        return $response;
    }
}
