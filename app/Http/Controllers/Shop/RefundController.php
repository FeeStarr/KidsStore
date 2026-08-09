<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function __construct(private RefundService $refunds)
    {
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);

        $data = $request->validate([
            'scope'              => ['required', 'in:full,item'],
            'order_item_id'      => ['required_if:scope,item', 'nullable', 'exists:order_items,id'],
            'quantity'           => ['required_if:scope,item', 'nullable', 'integer', 'min:1'],
            'reason'             => ['required', 'string', Rule::in(array_keys(RefundRequest::REASONS))],
            'details'            => ['nullable', 'string', 'max:1000'],
            'evidence'           => ['nullable', 'file', 'image', 'max:5120'],
            'request_type'       => ['required', 'in:refund,exchange'],
            'exchange_variant_id'=> ['required_if:request_type,exchange', 'nullable', 'exists:product_variants,id'],
        ]);

        $item     = null;
        $quantity = 1;

        if ($data['scope'] === 'item') {
            $item = OrderItem::where('id', $data['order_item_id'])
                ->where('order_id', $order->id)
                ->firstOrFail();
            $quantity = (int) ($data['quantity'] ?? $item->quantity);
            $quantity = min($quantity, $item->quantity);
        }

        $exchangeVariant = null;
        if (($data['request_type'] ?? 'refund') === 'exchange' && ! empty($data['exchange_variant_id'])) {
            $exchangeVariant = ProductVariant::findOrFail($data['exchange_variant_id']);
        }

        try {
            $this->refunds->request(
                $order,
                $data['reason'],
                $data['details'] ?? null,
                $item,
                $quantity,
                $request->hasFile('evidence') ? $request->file('evidence') : null,
                $exchangeVariant
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return request submitted. We will review it shortly.');
    }

    public function uploadEvidence(Request $request, Order $order, RefundRequest $refundRequest): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);
        abort_unless((int) $refundRequest->order_id === (int) $order->id, 404);

        $data = $request->validate([
            'evidence'      => ['nullable', 'file', 'image', 'max:5120'],
            'evidence_video'=> ['nullable', 'file', 'mimes:mp4,mov,avi,webm', 'max:20480'],
            'details'       => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->refunds->uploadEvidence(
                $refundRequest,
                $request->hasFile('evidence') ? $request->file('evidence') : null,
                $request->hasFile('evidence_video') ? $request->file('evidence_video') : null,
                $data['details'] ?? null
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Evidence uploaded. Your return request is now under review.');
    }

    public function cancel(Request $request, Order $order, RefundRequest $refundRequest): RedirectResponse
    {
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);
        abort_unless((int) $refundRequest->order_id === (int) $order->id, 404);

        try {
            $this->refunds->cancel($refundRequest, Auth::user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return request cancelled.');
    }
}
