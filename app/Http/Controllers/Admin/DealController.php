<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DealRequest;
use App\Models\Deal;
use App\Models\Product;
use App\Services\DealService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DealController extends Controller
{
    private const IMAGE_DISK = 'public';
    private const IMAGE_DIR  = 'deals';

    public function __construct(private DealService $deals)
    {
    }

    public function index()
    {
        $deals = Deal::with('products')->withCount('products')->latest()->get();

        return view('admin.deals.index', compact('deals'));
    }

    public function create()
    {
        $products = Product::with('primaryImage', 'defaultVariant')
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere(function ($legacy) {
                      $legacy->whereNull('status')->where('is_active', true);
                  });
            })
            ->orderBy('name')
            ->get();

        return view('admin.deals.create', compact('products'));
    }

    public function store(DealRequest $request): RedirectResponse
    {
        $data = $this->normalize($request);

        try {
            $deal = $this->deals->create($data, $request->input('product_ids', []));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.deals.show', $deal)->with('success', 'Deal created.');
    }

    public function show(Deal $deal)
    {
        $deal->load('products.primaryImage', 'products.defaultVariant', 'createdBy');

        return view('admin.deals.show', compact('deal'));
    }

    public function edit(Deal $deal)
    {
        $products = Product::with('primaryImage', 'defaultVariant')
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhere(function ($legacy) {
                      $legacy->whereNull('status')->where('is_active', true);
                  });
            })
            ->orderBy('name')
            ->get();
        $deal->load('products');

        return view('admin.deals.edit', compact('deal', 'products'));
    }

    public function update(DealRequest $request, Deal $deal): RedirectResponse
    {
        $data = $this->normalize($request, $deal);

        try {
            $this->deals->update($deal, $data, $request->input('product_ids', []));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.deals.show', $deal->fresh())->with('success', 'Deal updated.');
    }

    public function cancel(Deal $deal): RedirectResponse
    {
        $this->deals->cancel($deal);

        return redirect()->route('admin.deals.index')->with('success', 'Deal cancelled. Discounts removed.');
    }

    public function duplicate(Deal $deal): RedirectResponse
    {
        $copy = $this->deals->duplicate($deal);

        return redirect()->route('admin.deals.edit', $copy)->with('success', 'Deal duplicated as a draft.');
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $this->deals->delete($deal);

        return redirect()->route('admin.deals.index')->with('success', 'Deal deleted. Historical order pricing is unchanged.');
    }

    private function normalize(DealRequest $request, ?Deal $deal = null): array
    {
        $data = $request->validated();
        unset($data['product_ids']);

        $data['slug'] = $data['slug'] ?? Str::slug($request->input('title')).'-'.Str::lower(Str::random(4));

        // Schedule: active means the window is currently open; otherwise the clock decides.
        $status = $request->input('status');
        if (! $status) {
            $status = $request->input('starts_at') > now()
                ? Deal::STATUS_SCHEDULED
                : Deal::STATUS_ACTIVE;
        }
        $data['status'] = $status;

        $data['starts_at'] = $this->parseDateTime($request->input('starts_at'));
        $data['ends_at']   = $this->parseDateTime($request->input('ends_at'));

        // Images: replace only when a new file is uploaded.
        $data['banner_image']    = $deal?->banner_image;
        $data['thumbnail_image'] = $deal?->thumbnail_image;
        if ($request->hasFile('banner_image')) {
            $data['banner_image'] = $request->file('banner_image')->store(self::IMAGE_DIR, self::IMAGE_DISK);
        }
        if ($request->hasFile('thumbnail_image')) {
            $data['thumbnail_image'] = $request->file('thumbnail_image')->store(self::IMAGE_DIR, self::IMAGE_DISK);
        }

        $data['created_by'] = $deal?->created_by ?? auth()->id();

        return $data;
    }

    private function parseDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($value));
    }
}
