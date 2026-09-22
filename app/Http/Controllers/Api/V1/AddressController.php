<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends BaseController
{
    /**
     * GET /api/v1/addresses
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return AddressResource::collection($addresses);
    }

    /**
     * POST /api/v1/addresses
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'       => ['required', 'string', 'max:100'],
            'line1'       => ['required', 'string', 'max:255'],
            'line2'       => ['nullable', 'string', 'max:255'],
            'city'        => ['required', 'string', 'max:100'],
            'state'       => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country'     => ['nullable', 'string', 'max:100'],
            'is_default'  => ['sometimes', 'boolean'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['country'] = $data['country'] ?? 'Nigeria';

        // If this is the first address or marked as default, unset other defaults
        if (! empty($data['is_default']) && $data['is_default']) {
            $request->user()->addresses()->where('is_default', true)->update(['is_default' => false]);
        }

        // If this is the user's first address, make it default automatically
        if ($request->user()->addresses()->count() === 0) {
            $data['is_default'] = true;
        }

        $address = Address::create($data);

        return $this->createdResponse(new AddressResource($address), 'Address created successfully.');
    }

    /**
     * PATCH /api/v1/addresses/{address}
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        if ((int) $address->user_id !== (int) $request->user()->id) {
            return $this->forbiddenResponse('You do not have access to this address.');
        }

        $data = $request->validate([
            'label'       => ['sometimes', 'string', 'max:100'],
            'line1'       => ['sometimes', 'string', 'max:255'],
            'line2'       => ['nullable', 'string', 'max:255'],
            'city'        => ['sometimes', 'string', 'max:100'],
            'state'       => ['sometimes', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country'     => ['nullable', 'string', 'max:100'],
        ]);

        $address->update($data);

        return $this->successResponse(new AddressResource($address->fresh()), 'Address updated successfully.');
    }

    /**
     * DELETE /api/v1/addresses/{address}
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        if ((int) $address->user_id !== (int) $request->user()->id) {
            return $this->forbiddenResponse('You do not have access to this address.');
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // If deleted address was default, make the most recent address default
        if ($wasDefault) {
            $newDefault = $request->user()->addresses()->latest()->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return $this->successResponse(null, 'Address deleted successfully.');
    }

    /**
     * PATCH /api/v1/addresses/{address}/default
     */
    public function setDefault(Request $request, Address $address): JsonResponse
    {
        if ((int) $address->user_id !== (int) $request->user()->id) {
            return $this->forbiddenResponse('You do not have access to this address.');
        }

        $request->user()->addresses()->where('is_default', true)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return $this->successResponse(new AddressResource($address->fresh()), 'Default address updated.');
    }
}
