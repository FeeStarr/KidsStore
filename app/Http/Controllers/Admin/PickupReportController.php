<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickupReport;
use Illuminate\Http\Request;

class PickupReportController extends Controller
{
    public function index(Request $request)
    {
        $query = PickupReport::with(['station', 'order'])
            ->orderByDesc('created_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $reports = $query->paginate(25);

        $stats = [
            'open'          => PickupReport::where('status', 'open')->count(),
            'investigating' => PickupReport::where('status', 'investigating')->count(),
            'resolved'      => PickupReport::where('status', 'resolved')->count(),
            'total'         => PickupReport::count(),
        ];

        return view('admin.pickup-reports.index', compact('reports', 'stats'));
    }

    public function show(PickupReport $report)
    {
        $report->load(['station', 'order', 'orderItem', 'order.customer']);
        return view('admin.pickup-reports.show', compact('report'));
    }

    public function update(Request $request, PickupReport $report)
    {
        $data = $request->validate([
            'status'      => ['required', 'in:open,investigating,resolved,dismissed'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update($data);

        return back()->with('success', 'Report updated.');
    }
}
