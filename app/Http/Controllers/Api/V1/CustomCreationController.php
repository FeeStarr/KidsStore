<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CustomCreationResource;
use App\Models\CustomCreation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomCreationController extends BaseController
{
    /**
     * GET /api/v1/custom-creations
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CustomCreation::active()->orderByDesc('sort_order');

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        $creations = $query->paginate($request->input('per_page', 12));

        return CustomCreationResource::collection($creations);
    }

    /**
     * GET /api/v1/custom-creations/{customCreation}
     */
    public function show(CustomCreation $customCreation): JsonResponse
    {
        if (! $customCreation->is_active) {
            return $this->notFoundResponse('Custom creation not found.');
        }

        return $this->successResponse(new CustomCreationResource($customCreation));
    }
}
