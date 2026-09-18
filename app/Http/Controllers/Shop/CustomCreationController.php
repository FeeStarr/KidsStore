<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomCreation;
use Illuminate\Http\Request;

class CustomCreationController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomCreation::active()
            ->select([
                'id',
                'title',
                'image_path',
                'price',
                'is_price_from',
                'description',
                'category',
            ]);

        $category = $request->input('category');

        if ($category && array_key_exists($category, CustomCreation::CATEGORIES)) {
            $query->where('category', $category);
        }

        $creations = $query->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('shop.custom-creations.index', [
            'creations' => $creations,
            'categories' => CustomCreation::CATEGORIES,
            'activeCategory' => $category,
        ]);
    }

    public function show(int $id)
    {
        $creation = CustomCreation::active()
            ->where('id', $id)
            ->select([
                'id',
                'title',
                'image_path',
                'price',
                'is_price_from',
                'description',
                'category',
            ])
            ->firstOrFail();

        return view('shop.custom-creations.show', [
            'creation' => $creation,
            'categories' => CustomCreation::CATEGORIES,
        ]);
    }
}
