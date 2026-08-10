<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\AgeRange;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
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
        $brands = Brand::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.create', compact('categories', 'brands'));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $this->mergeVariantFilesIntoData($request);

        $this->products->create(
            $data,
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
        $product->load(
            'images',
            'variants.inventory',
            'variants.images',
            'variants.ageRange',
            'variants.sizeRef',
            'variants.colorRef'
        );
        $categories = Category::orderBy('name')->get();
        $brands = Brand::where('is_active', true)->orderBy('name')->get();
        $ageRanges = AgeRange::where('is_active', true)->orderBy('name')->get();
        $sizes = Size::where('is_active', true)->orderBy('name')->get();
        $colors = Color::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'categories', 'brands', 'ageRanges', 'sizes', 'colors'));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $this->mergeVariantFilesIntoData($request);

        $this->products->update(
            $product,
            $data,
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

    private function mergeVariantFilesIntoData(ProductRequest $request): array
    {
        $validated = $request->validated();

        $variantFiles = $request->file('variants', []);

        if (empty($variantFiles) || ! is_array($variantFiles)) {
            return $validated;
        }

        foreach ($variantFiles as $vi => $vf) {
            if (! isset($validated['variants'][$vi])) {
                continue;
            }

            if (! is_array($vf)) {
                continue;
            }

            if (isset($vf['images']) && is_array($vf['images'])) {
                $validated['variants'][$vi]['images'] = $vf['images'];
            }

            if (isset($vf['sizes']) && is_array($vf['sizes'])) {
                foreach ($vf['sizes'] as $si => $sv) {
                    if (isset($sv['images']) && is_array($sv['images'])) {
                        $validated['variants'][$vi]['sizes'][$si]['images'] = $sv['images'];
                    }
                }
            }
        }

        return $validated;
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        if ($product->isInactive()) {
            $product->activate();
            $message = 'Product activated.';
        } else {
            $product->deactivate();
            $message = 'Product deactivated. All variants are now inactive.';
        }

        return back()->with('success', $message);
    }
}
