<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorApproval;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class VendorApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $query = VendorApproval::with('user');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $approvals = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        return view('admin.vendor_approvals.index', compact('approvals', 'status'));
    }

    public function show(VendorApproval $vendorApproval): View
    {
        $vendorApproval->load(['user', 'reviewer']);

        return view('admin.vendor_approvals.show', compact('vendorApproval'));
    }

    public function review(Request $request, VendorApproval $vendorApproval): RedirectResponse
    {
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'notes' => 'nullable|string']);
        $vendorApproval->status = $data['status'];
        $vendorApproval->notes = $data['notes'] ?? null;
        $vendorApproval->reviewed_by = auth()->id();
        $vendorApproval->reviewed_at = now();
        $vendorApproval->save();

        if ($data['status'] === 'approved') {
            $user = $vendorApproval->user;
            if ($user) {
                $existingRoles = $user->roles()->pluck('name')->all();
                if (empty($existingRoles) && $user->role) {
                    $existingRoles = [$user->role];
                }
                $existingRoles[] = User::ROLE_VENDOR;
                $user->syncRoles(array_values(array_unique($existingRoles)));
            }
        }

        return redirect()->back()->with('success', 'Vendor review updated');
    }
}
