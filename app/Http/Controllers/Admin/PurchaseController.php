<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest;
use App\Models\Product;
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
        $products  = Product::with(['variants' => fn ($q) => $q->where('is_active', true)->orderBy('id')])
            ->where('is_active', true)->orderBy('name')->get();

        return view('admin.purchases.create', compact('suppliers', 'products'));
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
