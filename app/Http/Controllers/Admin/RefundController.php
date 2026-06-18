<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefundController extends Controller
{
    public function __construct(private RefundService $refunds)
    {
    }

    public function index(): View
    {
        $requests = RefundRequest::with([
            'order.customer',
            'orderItem.product',
            'reviewer',
        ])->latest()->get();

        $pending  = $requests->where('status', 'pending')->count();

        return view('admin.refunds.index', compact('requests', 'pending'));
    }

    public function show(RefundRequest $refundRequest): View
    {
        $refundRequest->load([
            'order.customer',
            'order.items.product',
            'order.items.variant',
            'orderItem.product',
            'orderItem.variant',
            'reviewer',
        ]);

        return view('admin.refunds.show', compact('refundRequest'));
    }

    public function approve(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->refunds->approve($refundRequest, Auth::user(), $data['admin_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $refundRequest->fresh()->status === 'refunded'
            ? 'Refund approved and processed successfully.'
            : 'Refund approved but OPay processing failed — check the request for details.';

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', $msg);
    }

    public function reject(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->refunds->reject($refundRequest, Auth::user(), $data['admin_note']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Refund request rejected.');
    }
}
