<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DealResource;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DealController extends BaseController
{
    /**
     * GET /api/v1/deals
     */
    public function index(): AnonymousResourceCollection
    {
        $deals = Deal::live()
            ->with(['products' => function ($q) {
                $q->where('products.is_active', true)
                    ->with(['primaryImage', 'variants.inventory']);
            }])
            ->orderByDesc('is_featured')
            ->orderByDesc('starts_at')
            ->get();

        return DealResource::collection($deals);
    }
}
