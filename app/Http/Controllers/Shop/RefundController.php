<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
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
        // Only the order owner
        abort_unless((int) $order->customer_id === (int) Auth::id(), 403);

        $data = $request->validate([
            'scope'          => ['required', 'in:full,item'],
            'order_item_id'  => ['required_if:scope,item', 'nullable', 'exists:order_items,id'],
            'quantity'       => ['required_if:scope,item', 'nullable', 'integer', 'min:1'],
            'reason'         => ['required', 'string', Rule::in(array_keys(RefundRequest::REASONS))],
            'details'        => ['nullable', 'string', 'max:1000'],
            'evidence'       => ['nullable', 'file', 'image', 'max:5120'], // 5 MB
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

        try {
            $this->refunds->request(
                $order,
                $data['reason'],
                $data['details'] ?? null,
                $item,
                $quantity,
                $request->hasFile('evidence') ? $request->file('evidence') : null
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund request submitted. We will review it within 2–3 business days.');
    }
}
