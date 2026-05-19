<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfitReportController extends Controller
{
    /**
     * Net profit reporting based on actual sold orders. Profit = (unit_price - discount%) - landed_unit_cost,
     * multiplied by quantity. Cancelled orders are excluded; only confirmed/shipped/delivered count.
     */
    public function index(Request $request)
    {
        abort_if(! auth()->check() || ! auth()->user()->hasRole(User::ROLE_SUPERADMIN), 403);

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();
        $to   = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $countedStatuses = ['confirmed', 'processing', 'shipped', 'ready for pick up', 'delivered'];

        // Profit expression: ((unit_price * (1 - discount/100)) - landed_unit_cost) * quantity
        $profitExpr  = '((order_items.unit_price * (1 - order_items.discount / 100)) - order_items.landed_unit_cost) * order_items.quantity';
        $revenueExpr = '(order_items.unit_price * (1 - order_items.discount / 100)) * order_items.quantity';
        $cogsExpr    = 'order_items.landed_unit_cost * order_items.quantity';

        $base = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.order_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('orders.status', $countedStatuses);

        $totals = (clone $base)
            ->selectRaw("COALESCE(SUM($revenueExpr),0) as revenue,
                         COALESCE(SUM($cogsExpr),0)    as cogs,
                         COALESCE(SUM($profitExpr),0)  as profit,
                         COALESCE(SUM(order_items.quantity),0) as units_sold,
                         COUNT(DISTINCT orders.id) as order_count")
            ->first();

        $byProduct = (clone $base)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->selectRaw("products.id, products.name, products.sku,
                         SUM(order_items.quantity) as units_sold,
                         SUM($revenueExpr) as revenue,
                         SUM($cogsExpr)    as cogs,
                         SUM($profitExpr)  as profit")
            ->orderByDesc('profit')
            ->limit(50)
            ->get();

        $byDay = (clone $base)
            ->groupBy('orders.order_date')
            ->selectRaw("orders.order_date as day,
                         SUM($revenueExpr) as revenue,
                         SUM($cogsExpr)    as cogs,
                         SUM($profitExpr)  as profit,
                         SUM(order_items.quantity) as units_sold")
            ->orderBy('orders.order_date')
            ->get();

        return view('admin.reports.profit', [
            'from'       => $from,
            'to'         => $to,
            'totals'     => $totals,
            'byProduct'  => $byProduct,
            'byDay'      => $byDay,
        ]);
    }
}
