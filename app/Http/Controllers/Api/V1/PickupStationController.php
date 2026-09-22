<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\PickupStationResource;
use App\Models\PickupStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PickupStationController extends BaseController
{
    /**
     * GET /api/v1/pickup-stations
     */
    public function index(): AnonymousResourceCollection
    {
        $stations = PickupStation::where('is_active', true)
            ->orderBy('name')
            ->get();

        return PickupStationResource::collection($stations);
    }

    /**
     * GET /api/v1/pickup-stations/{pickupStation}
     */
    public function show(PickupStation $pickupStation): JsonResponse
    {
        if (! $pickupStation->is_active) {
            return $this->notFoundResponse('Pickup station not found.');
        }

        return $this->successResponse(new PickupStationResource($pickupStation));
    }
}
