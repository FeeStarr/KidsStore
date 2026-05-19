<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $products)
    {
    }

    public function index(Request $request): View
    {
        $query = Product::with(['category', 'primaryImage', 'variants.inventory']);

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->latest()->get();

        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        $categories = Category::orderBy('name')->get();

        return view('admin.products.create', compact('categories'));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->products->create(
            $request->validated(),
            $request->file('images', [])
        );

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function show(Product $product): View
    {
        $product->load('images', 'category', 'inventory', 'inventoryMovements');

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $product->load('images');
        $categories = Category::orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->products->update(
            $product,
            $request->validated(),
            $request->file('images', []),
            $request->input('delete_images', [])
        );

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->products->delete($product);

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function setPrimaryImage(Product $product, int $imageId): RedirectResponse
    {
        $this->products->setPrimaryImage($product, $imageId);

        return back()->with('success', 'Primary image updated.');
    }
}
