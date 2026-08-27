<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class InventoryController extends Controller
{
    public function __construct(private InventoryServiceInterface $inventory) {}

    public function index(Request $request): View
    {
        $query = Inventory::with(['product', 'variant.product', 'variant.image', 'variant.ageRange', 'variant.sizeRef', 'variant.colorRef']);

        if ($request->boolean('low_stock')) {
            $query->whereRaw('COALESCE(quantity_on_hand, quantity) <= reorder_level');
        }

        $inventories = $query->limit(2000)->get();

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
            'quantity'  => ['required', 'integer', 'min:1'],
            'reason'    => ['required', 'string', 'max:120'],
            'note'      => ['nullable', 'string', 'max:500'],
        ]);

        $variant = $inventory->variant;
        if (! $variant) {
            return back()->with('error', 'This inventory row is not linked to a variant.');
        }

        try {
            $this->inventory->adjustStock(
                $variant,
                -$data['quantity'],
                $data['reason'],
                $data['note'] ?? null
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stock decreased by '.$data['quantity'].' for '.$variant->display_label.'.');
    }
}
