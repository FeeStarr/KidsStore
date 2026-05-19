<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\AboutController as AdminAboutController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ProfitReportController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorApprovalController;
use App\Http\Controllers\Shop\AboutController as ShopAboutController;
use App\Http\Controllers\Shop\ContactController as ShopContactController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\AuthController as ShopAuthController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Default Login Redirect (for auth middleware)
|--------------------------------------------------------------------------
*/
Route::get('/login', function () {
    return redirect()->route('shop.login');
})->name('login');

/*
|--------------------------------------------------------------------------
| Public Storefront
|--------------------------------------------------------------------------
*/
Route::name('shop.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [ShopAboutController::class, 'show'])->name('about');
    Route::get('/contact', [ShopContactController::class, 'show'])->name('contact');
    Route::post('/contact', [ShopContactController::class, 'send'])->name('contact.send');
    Route::get('/shop', [ShopController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/{variant}', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{variant}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{variant}', [CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

    Route::middleware('guest')->group(function () {
        Route::get('/login',    [ShopAuthController::class, 'showLogin'])->name('login');
        Route::post('/login',   [ShopAuthController::class, 'login']);
        Route::get('/register', [ShopAuthController::class, 'showRegister'])->name('register');
        Route::post('/register',[ShopAuthController::class, 'register']);
    });
    Route::post('/logout', [ShopAuthController::class, 'logout'])
        ->middleware('auth')->name('logout');

    Route::middleware('auth')->group(function () {
        Route::get('/checkout',  [CheckoutController::class, 'show'])->name('checkout.show');
        Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');

        Route::get('/account/orders',          [AccountController::class, 'orders'])->name('account.orders.index');
        Route::get('/account/orders/{order}',  [AccountController::class, 'showOrder'])->name('account.orders.show');

        Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
        Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');

        Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
        Route::put('/account/addresses/{address}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
        Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
        Route::post('/account/addresses/{address}/default', [AccountController::class, 'setDefaultAddress'])->name('account.addresses.default');

        Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->name('products.reviews.store');
    });
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    // Admin authentication (outside auth middleware)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login']);
        Route::get('/login/2fa', [AdminAuthController::class, 'showTwoFactorForm'])->name('login.2fa');
        Route::post('/login/2fa', [AdminAuthController::class, 'verifyTwoFactor'])->name('login.verify-2fa');
    });
    Route::post('/logout', [AdminAuthController::class, 'logout'])
        ->middleware('auth.admin')->name('logout');

    // Protected admin routes
    Route::middleware('auth.admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('about', [AdminAboutController::class, 'edit'])->name('about.edit');
        Route::put('about', [AdminAboutController::class, 'update'])->name('about.update');

        Route::get('contact', [AdminContactController::class, 'edit'])->name('contact.edit');
        Route::put('contact', [AdminContactController::class, 'update'])->name('contact.update');
        Route::get('contact/messages', [AdminContactController::class, 'messages'])->name('contact.messages');
        Route::get('contact/messages/{message}', [AdminContactController::class, 'showMessage'])->name('contact.messages.show');
        Route::delete('contact/messages/{message}', [AdminContactController::class, 'destroyMessage'])->name('contact.messages.destroy');

        Route::resource('products', ProductController::class);
        Route::post('products/{product}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage'])
            ->name('products.images.primary');

        // Product variants (nested store; shallow update/delete by variant id)
        Route::post('products/{product}/variants',     [ProductVariantController::class, 'store'])
            ->name('products.variants.store');
        Route::put('variants/{variant}',               [ProductVariantController::class, 'update'])
            ->name('variants.update');
        Route::delete('variants/{variant}',            [ProductVariantController::class, 'destroy'])
            ->name('variants.destroy');

        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::patch('inventory/{inventory}/reorder-level', [InventoryController::class, 'updateReorderLevel'])
            ->name('inventory.reorder');
        Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])
            ->name('inventory.adjust');

        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');

        Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/processing', [OrderController::class, 'processing'])->name('orders.processing');
        Route::post('orders/{order}/ship', [OrderController::class, 'ship'])->name('orders.ship');
        Route::post('orders/{order}/ready-for-pickup', [OrderController::class, 'readyForPickup'])->name('orders.ready-for-pickup');
        Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::post('orders/{order}/payments', [OrderController::class, 'storePayment'])->name('orders.payments.store');

        Route::resource('users', UserController::class)->except(['destroy'])->middleware('permission:manage_customers');
        Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])
            ->name('users.assign-role')->middleware('permission:manage_customers');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active')->middleware('permission:manage_customers');
        Route::put('users/{user}/profile', [UserController::class, 'updateProfile'])
            ->name('users.profile.update')->middleware('permission:manage_customers');
        Route::post('users/{user}/addresses', [UserController::class, 'storeAddress'])
            ->name('users.addresses.store')->middleware('permission:manage_customers');
        Route::put('users/{user}/addresses/{address}', [UserController::class, 'updateAddress'])
            ->name('users.addresses.update')->middleware('permission:manage_customers');
        Route::delete('users/{user}/addresses/{address}', [UserController::class, 'destroyAddress'])
            ->name('users.addresses.destroy')->middleware('permission:manage_customers');
        Route::post('users/{user}/addresses/{address}/default', [UserController::class, 'setDefaultAddress'])
            ->name('users.addresses.default')->middleware('permission:manage_customers');

        Route::resource('vendor-approvals', VendorApprovalController::class)->only(['index', 'show'])
            ->middleware('permission:manage_vendors');
        Route::post('vendor-approvals/{vendorApproval}/review', [VendorApprovalController::class, 'review'])
            ->name('vendor-approvals.review')->middleware('permission:manage_vendors');

        Route::get('reports/profit', [ProfitReportController::class, 'index'])
            ->name('reports.profit')->middleware('permission:view_reports');
    });
});
