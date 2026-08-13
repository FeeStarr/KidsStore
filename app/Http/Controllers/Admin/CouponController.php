<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\CouponService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private CouponService $coupons)
    {
    }

    public function index(Request $request)
    {
        $coupons = Coupon::withCount(['usages', 'products', 'variants'])
            ->withTrashed()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($query) use ($request) {
                $query->where('code', 'like', '%'.strtolower($request->input('q')).'%')
                    ->orWhere('name', 'like', '%'.$request->input('q').'%');
            }))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('discount_type'), fn ($q) => $q->where('discount_type', $request->input('discount_type')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create()
    {
        $products = $this->productList();
        $coupon = new Coupon();

        return view('admin.coupons.create', compact('products', 'coupon'));
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        $data = $this->normalize($request);

        $coupon = $this->coupons->create(
            $data,
            $request->input('product_ids', []),
            $request->input('variant_ids', [])
        );

        return redirect()->route('admin.coupons.show', $coupon)->with('success', 'Coupon created.');
    }

    public function show(Coupon $coupon)
    {
        $coupon->load('products.defaultVariant', 'variants.product', 'createdBy');

        return view('admin.coupons.show', compact('coupon'));
    }

    public function edit(Coupon $coupon)
    {
        $products = $this->productList();
        $coupon->load('products', 'variants');

        return view('admin.coupons.edit', compact('coupon', 'products'));
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $data = $this->normalize($request, $coupon);

        $this->coupons->update(
            $coupon,
            $data,
            $request->input('product_ids', []),
            $request->input('variant_ids', [])
        );

        return redirect()->route('admin.coupons.show', $coupon->fresh())->with('success', 'Coupon updated.');
    }

    public function activate(Coupon $coupon): RedirectResponse
    {
        $this->coupons->activate($coupon);

        return redirect()->back()->with('success', 'Coupon activated.');
    }

    public function deactivate(Coupon $coupon): RedirectResponse
    {
        $this->coupons->deactivate($coupon);

        return redirect()->back()->with('success', 'Coupon deactivated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $this->coupons->delete($coupon);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon archived. Historical order pricing is unchanged.');
    }

    private function normalize(CouponRequest $request, ?Coupon $coupon = null): array
    {
        $data = $request->validated();
        unset($data['product_ids'], $data['variant_ids']);

        $data['minimum_order_amount']    = $this->nullableNumeric($data['minimum_order_amount'] ?? null);
        $data['maximum_discount_amount'] = $this->nullableNumeric($data['maximum_discount_amount'] ?? null);
        $data['starts_at']               = $this->parseDateTime($data['starts_at'] ?? null);
        $data['ends_at']                 = $this->parseDateTime($data['ends_at'] ?? null);
        $data['usage_limit']             = isset($data['usage_limit']) && $data['usage_limit'] !== null && $data['usage_limit'] !== ''
            ? (int) $data['usage_limit']
            : null;
        $data['created_by'] = $coupon?->created_by ?? auth()->id();

        return $data;
    }

    private function nullableNumeric(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }

    private function parseDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($value));
    }

    private function productList()
    {
        return Product::with('primaryImage', 'defaultVariant')
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere(function ($legacy) {
                      $legacy->whereNull('status')->where('is_active', true);
                  });
            })
            ->orderBy('name')
            ->get();
    }
}