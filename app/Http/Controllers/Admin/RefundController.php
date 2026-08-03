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

        $pending = $requests->whereIn('status', [
            RefundRequest::STATUS_REQUESTED,
            RefundRequest::STATUS_PENDING_REVIEW,
            RefundRequest::STATUS_AWAITING_EVIDENCE,
        ])->count();

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
            'inspector',
            'auditLogs.user',
        ]);

        return view('admin.refunds.show', compact('refundRequest'));
    }

    public function requestEvidence(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->refunds->requestEvidence($refundRequest, Auth::user(), $data['admin_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Evidence request sent to customer.');
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

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Return approved. Awaiting item shipment.');
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

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Return request rejected.');
    }

    public function markReceived(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->refunds->markReceived($refundRequest, Auth::user(), $data['admin_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Item marked as received. Stock restored.');
    }

    public function inspect(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $data = $request->validate([
            'outcome'     => ['required', 'in:refund,replacement'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->refunds->inspect($refundRequest, Auth::user(), $data['outcome'], $data['notes'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $data['outcome'] === 'refund'
            ? 'Inspection complete. Refund approved for processing.'
            : 'Inspection complete. Replacement approved.';

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', $msg);
    }

    public function processRefund(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->refunds->processRefund($refundRequest, Auth::user(), $data['admin_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $refundRequest->fresh()->status === RefundRequest::STATUS_REFUNDED
            ? 'Refund processed successfully.'
            : 'Refund processing initiated — check Paystack status.';

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', $msg);
    }
}
