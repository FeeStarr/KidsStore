<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends BaseController
{
    /**
     * GET /api/v1/categories
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::with(['children' => function ($q) {
            $q->where('is_active', true);
        }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    /**
     * GET /api/v1/categories/{category}
     */
    public function show(Category $category): JsonResponse
    {
        if (! $category->is_active) {
            return $this->notFoundResponse('Category not found.');
        }

        $category->load(['children' => function ($q) {
            $q->where('is_active', true);
        }]);

        return $this->successResponse(new CategoryResource($category));
    }
}
