<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'products'        => Product::count(),
            'low_stock'       => Inventory::whereColumn('quantity', '<=', 'reorder_level')->count(),
            'pending_orders'  => Order::where('status', 'pending')->orWhere('status', 'order placed')->count(),
            'pending_purchases' => Purchase::where('status', 'pending')->count(),
            'revenue_total'   => (float) Payment::sum('amount'),
            'orders_total'    => (float) Order::sum(DB::raw('COALESCE(total_amount, grand_total)')),
        ];

        $recentOrders = Order::with('customer')->latest()->take(5)->get();
        $lowStock     = Inventory::with('product')->whereColumn('quantity', '<=', 'reorder_level')->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'lowStock'));
    }
}
