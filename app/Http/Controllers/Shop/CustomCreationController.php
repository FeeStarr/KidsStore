<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use Illuminate\Http\Request;

class CustomCreationController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomOrder::showcased()
            ->whereNotNull('showcase_image_path')
            ->select([
                'id',
                'showcase_title',
                'showcase_price',
                'showcase_description',
                'showcase_image_path',
                'showcase_category',
            ]);

        $category = $request->input('category');

        if ($category && array_key_exists($category, CustomOrder::SHOWCASE_CATEGORIES)) {
            $query->where('showcase_category', $category);
        }

        $creations = $query->orderByDesc('completed_at')->paginate(12)->withQueryString();

        return view('shop.custom-creations.index', [
            'creations' => $creations,
            'categories' => CustomOrder::SHOWCASE_CATEGORIES,
            'activeCategory' => $category,
        ]);
    }

    public function show(int $id)
    {
        $creation = CustomOrder::showcased()
            ->where('id', $id)
            ->whereNotNull('showcase_image_path')
            ->select([
                'id',
                'showcase_title',
                'showcase_price',
                'showcase_description',
                'showcase_image_path',
                'showcase_category',
                'completed_at',
            ])
            ->firstOrFail();

        return view('shop.custom-creations.show', [
            'creation' => $creation,
            'categories' => CustomOrder::SHOWCASE_CATEGORIES,
        ]);
    }
}
