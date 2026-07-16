<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest;
use App\Models\Product;
use App\Models\PickupStation;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases)
    {
    }

    public function index(): View
    {
        $purchases = Purchase::with('supplier')->latest()->get();

        return view('admin.purchases.index', compact('purchases'));
    }

    public function create(): View
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products  = Product::with([
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
                'variants.colorRef',
                'variants.sizeRef',
                'variants.ageRange',
            ])
            ->where('is_active', true)->orderBy('name')->get();

        $avgPickupPct = (float) PickupStation::where('is_active', true)->max('fee_pct') ?: 0;

        return view('admin.purchases.create', compact('suppliers', 'products', 'avgPickupPct'));
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $purchase = $this->purchases->create($request->validated());

        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Purchase created.');
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load('items.product', 'items.variant', 'supplier');

        return view('admin.purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase): View|\Illuminate\Http\RedirectResponse
    {
        if ($purchase->status !== 'pending') {
            return redirect()->route('admin.purchases.show', $purchase)
                ->with('error', 'Only pending purchases can be edited.');
        }

        $purchase->load('items.variant.colorRef', 'items.variant.sizeRef', 'items.variant.ageRange');

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products  = Product::with([
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('id'),
                'variants.colorRef',
                'variants.sizeRef',
                'variants.ageRange',
            ])
            ->where('is_active', true)->orderBy('name')->get();

        $avgPickupPct = (float) PickupStation::where('is_active', true)->max('fee_pct') ?: 0;

        return view('admin.purchases.edit', compact('purchase', 'suppliers', 'products', 'avgPickupPct'));
    }

    public function update(PurchaseRequest $request, Purchase $purchase): \Illuminate\Http\RedirectResponse
    {
        $this->purchases->update($purchase, $request->validated());

        return redirect()->route('admin.purchases.show', $purchase)
            ->with('success', 'Purchase updated.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        if ($purchase->status !== 'pending') {
            return back()->with('error', 'Only pending purchases can be deleted.');
        }

        $this->purchases->delete($purchase);

        return redirect()->route('admin.purchases.index')
            ->with('success', 'Purchase deleted successfully.');
    }

    public function receive(Purchase $purchase): RedirectResponse
    {
        $this->purchases->markReceived($purchase);

        return back()->with('success', 'Purchase marked as received. Inventory updated.');
    }

    public function cancel(Purchase $purchase): RedirectResponse
    {
        $this->purchases->cancel($purchase);

        return back()->with('success', 'Purchase cancelled.');
    }
}
