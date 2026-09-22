<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\DeliveryLocationResource;
use App\Models\DeliveryLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryLocationController extends BaseController
{
    /**
     * GET /api/v1/delivery-locations
     */
    public function index(): AnonymousResourceCollection
    {
        $locations = DeliveryLocation::where('is_active', true)
            ->with(['charges' => function ($q) {
                $q->where('is_active', true);
            }])
            ->orderBy('name')
            ->get();

        return DeliveryLocationResource::collection($locations);
    }

    /**
     * GET /api/v1/delivery-locations/{deliveryLocation}
     */
    public function show(DeliveryLocation $deliveryLocation): JsonResponse
    {
        if (! $deliveryLocation->is_active) {
            return $this->notFoundResponse('Delivery location not found.');
        }

        $deliveryLocation->load(['charges' => function ($q) {
            $q->where('is_active', true);
        }]);

        return $this->successResponse(new DeliveryLocationResource($deliveryLocation));
    }
}
