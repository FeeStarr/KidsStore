<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/*
|--------------------------------------------------------------------------
|
| Mobile API for the KidsFlairr Flutter application.
| All routes are versioned under /api/v1.
|
*/

Route::prefix('v1')->group(function () {

    // ── Public (unauthenticated) routes ───────────────────────────────────

    // Auth
    Route::post('/auth/register', [\App\Http\Controllers\Api\V1\AuthController::class, 'register']);
    Route::post('/auth/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);

    // Products
    Route::get('/products', [\App\Http\Controllers\Api\V1\ProductController::class, 'index']);
    Route::get('/products/{product}', [\App\Http\Controllers\Api\V1\ProductController::class, 'show']);

    // Categories
    Route::get('/categories', [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);
    Route::get('/categories/{category}', [\App\Http\Controllers\Api\V1\CategoryController::class, 'show']);

    // Deals
    Route::get('/deals', [\App\Http\Controllers\Api\V1\DealController::class, 'index']);

    // Custom Creations
    Route::get('/custom-creations', [\App\Http\Controllers\Api\V1\CustomCreationController::class, 'index']);
    Route::get('/custom-creations/{customCreation}', [\App\Http\Controllers\Api\V1\CustomCreationController::class, 'show']);

    // Pickup Stations
    Route::get('/pickup-stations', [\App\Http\Controllers\Api\V1\PickupStationController::class, 'index']);
    Route::get('/pickup-stations/{pickupStation}', [\App\Http\Controllers\Api\V1\PickupStationController::class, 'show']);

    // Delivery Locations
    Route::get('/delivery-locations', [\App\Http\Controllers\Api\V1\DeliveryLocationController::class, 'index']);
    Route::get('/delivery-locations/{deliveryLocation}', [\App\Http\Controllers\Api\V1\DeliveryLocationController::class, 'show']);

    // ── Authenticated customer routes ─────────────────────────────────────

    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        Route::get('/auth/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);

        // Cart
        Route::get('/cart', [\App\Http\Controllers\Api\V1\CartController::class, 'index']);
        Route::post('/cart/items', [\App\Http\Controllers\Api\V1\CartController::class, 'addItem']);
        Route::patch('/cart/items/{lineKey}', [\App\Http\Controllers\Api\V1\CartController::class, 'updateItem']);
        Route::delete('/cart/items/{lineKey}', [\App\Http\Controllers\Api\V1\CartController::class, 'removeItem']);
        Route::delete('/cart', [\App\Http\Controllers\Api\V1\CartController::class, 'clear']);

        // Coupons
        Route::post('/coupons/validate', [\App\Http\Controllers\Api\V1\CouponController::class, 'validate']);
        Route::delete('/coupons', [\App\Http\Controllers\Api\V1\CouponController::class, 'remove']);

        // Checkout
        Route::post('/checkout', [\App\Http\Controllers\Api\V1\CheckoutController::class, 'place']);

        // Orders
        Route::get('/orders', [\App\Http\Controllers\Api\V1\OrderController::class, 'index']);
        Route::get('/orders/{order}', [\App\Http\Controllers\Api\V1\OrderController::class, 'show']);

        // Payments (Paystack)
        Route::post('/payments/paystack/initialize', [\App\Http\Controllers\Api\V1\PaymentController::class, 'paystackInitialize']);
        Route::get('/payments/{reference}', [\App\Http\Controllers\Api\V1\PaymentController::class, 'query']);

        // Profile
        Route::get('/profile', [\App\Http\Controllers\Api\V1\ProfileController::class, 'show']);
        Route::patch('/profile', [\App\Http\Controllers\Api\V1\ProfileController::class, 'update']);

        // Addresses
        Route::get('/addresses', [\App\Http\Controllers\Api\V1\AddressController::class, 'index']);
        Route::post('/addresses', [\App\Http\Controllers\Api\V1\AddressController::class, 'store']);
        Route::patch('/addresses/{address}', [\App\Http\Controllers\Api\V1\AddressController::class, 'update']);
        Route::delete('/addresses/{address}', [\App\Http\Controllers\Api\V1\AddressController::class, 'destroy']);
        Route::patch('/addresses/{address}/default', [\App\Http\Controllers\Api\V1\AddressController::class, 'setDefault']);

        // Reviews
        Route::post('/products/{product}/reviews', [\App\Http\Controllers\Api\V1\ReviewController::class, 'store']);
    });
});
