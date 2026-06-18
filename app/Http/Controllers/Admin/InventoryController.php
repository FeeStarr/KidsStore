<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class InventoryController extends Controller
{
    public function __construct(private InventoryServiceInterface $inventory) {}

    public function index(Request $request): View
    {
        $query = Inventory::with(['product', 'variant.product', 'variant.ageRange', 'variant.sizeRef', 'variant.colorRef']);

        if ($request->boolean('low_stock')) {
            $query->whereRaw('COALESCE(quantity_on_hand, quantity) <= reorder_level');
        }

        $inventories = $query->get();

        return view('admin.inventory.index', compact('inventories'));
    }

    public function updateReorderLevel(Request $request, Inventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'reorder_level' => ['required', 'integer', 'min:0'],
        ]);

        $inventory->update($data);

        return back()->with('success', 'Reorder level updated.');
    }

    public function adjust(Request $request, Inventory $inventory): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity'  => ['required', 'integer', 'min:1'],
            'reason'    => ['required', 'string', 'max:120'],
            'note'      => ['nullable', 'string', 'max:500'],
        ]);

        $delta = $data['direction'] === 'increase' ? $data['quantity'] : -$data['quantity'];

        $variant = $inventory->variant;
        if (! $variant) {
            return back()->with('error', 'This inventory row is not linked to a variant.');
        }

        try {
            $this->inventory->adjustStock(
                $variant,
                $delta,
                $data['reason'],
                $data['note'] ?? null
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stock adjusted: '.($delta > 0 ? '+' : '').$delta.' for '.$variant->display_label.'.');
    }
}
