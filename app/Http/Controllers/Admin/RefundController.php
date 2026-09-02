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
        ])->latest()->limit(2000)->get();

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

        $fresh = $refundRequest->fresh();
        $msg = $fresh->status === RefundRequest::STATUS_REFUNDED
            ? 'Refund processed successfully.'
            : ($fresh->status === RefundRequest::STATUS_REFUND_PROCESSING ? 'Refund accepted - awaiting Paystack webhook.' : 'Refund processing initiated - check Paystack status.');

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', $msg);
    }

    public function approveRefund(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        if ($refundRequest->status !== RefundRequest::STATUS_REFUND_REQUIRED) {
            return back()->with('error', 'Only refund-required requests can be approved.');
        }
        try {
            $refundRequest->update([
                'status' => RefundRequest::STATUS_REFUND_APPROVED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
            app(\App\Models\ReturnAuditLog::class)::create([
                'refund_request_id' => $refundRequest->id,
                'action' => 'approved',
                'user_id' => Auth::id(),
                'details' => $request->input('admin_note'),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Cancellation refund approved - ready to process.');
    }

    public function retryRefund(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        try {
            $this->refunds->retryRefund($refundRequest, Auth::user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Refund retry initiated.');
    }

    public function syncRefund(RefundRequest $refundRequest): RedirectResponse
    {
        try {
            $this->refunds->syncRefundStatus($refundRequest);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Refund status synced - now: ' . $refundRequest->fresh()->statusLabel);
    }

    public function markReplacementShipped(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->refunds->markReplacementShipped($refundRequest, Auth::user(), $data['admin_note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.refunds.show', $refundRequest)->with('success', 'Replacement marked as shipped.');
    }

    public function evidence(RefundRequest $refundRequest)
    {
        abort_unless($refundRequest->evidence_path, 404);

        $fullPath = storage_path('app/' . $refundRequest->evidence_path);

        if (!file_exists($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath, [
            'Content-Type' => mime_content_type($fullPath),
            'Content-Disposition' => 'inline',
        ]);
    }

    public function evidenceVideo(RefundRequest $refundRequest)
    {
        abort_unless($refundRequest->evidence_video_path, 404);

        $fullPath = storage_path('app/' . $refundRequest->evidence_video_path);

        if (!file_exists($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath, [
            'Content-Type' => mime_content_type($fullPath),
            'Content-Disposition' => 'inline',
        ]);
    }
}
