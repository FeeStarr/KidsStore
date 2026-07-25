<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\PasswordResetController as AdminPasswordResetController;
use App\Http\Controllers\Admin\AboutController as AdminAboutController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\ReturnPolicyController as AdminReturnPolicyController;
use App\Http\Controllers\Admin\PrivacyPolicyController as AdminPrivacyPolicyController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ProfitReportController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\PickupStationController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorApprovalController;
use App\Http\Controllers\PickupPortalController;
use App\Http\Controllers\OPayController;
use App\Http\Controllers\Shop\AboutController as ShopAboutController;
use App\Http\Controllers\Shop\ContactController as ShopContactController;
use App\Http\Controllers\Shop\ReturnPolicyController as ShopReturnPolicyController;
use App\Http\Controllers\Shop\PrivacyPolicyController as ShopPrivacyPolicyController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\AuthController as ShopAuthController;
use App\Http\Controllers\Shop\PasswordResetController as ShopPasswordResetController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\RefundController as ShopRefundController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\ReviewController;
use App\Http\Controllers\Shop\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Storefront
|--------------------------------------------------------------------------
*/
Route::name('shop.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [ShopAboutController::class, 'show'])->name('about');
    Route::get('/contact', [ShopContactController::class, 'show'])->name('contact');
    Route::get('/return-policy', [ShopReturnPolicyController::class, 'show'])->name('return-policy');
    Route::get('/privacy-policy', [ShopPrivacyPolicyController::class, 'show'])->name('privacy-policy');
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
        Route::get('/login/2fa',  [ShopAuthController::class, 'show2FA'])->name('2fa.show');
        Route::post('/login/2fa', [ShopAuthController::class, 'verify2FA'])->name('2fa.verify');
        
        // Password reset
        Route::get('/forgot-password',  [ShopPasswordResetController::class, 'showForgotForm'])->name('password.request');
        Route::post('/forgot-password', [ShopPasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::get('/reset-password/{token}', [ShopPasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password', [ShopPasswordResetController::class, 'resetPassword'])->name('password.update');
    });
    Route::post('/logout', [ShopAuthController::class, 'logout'])
        ->middleware('auth')->name('logout');

    Route::middleware('auth')->group(function () {
        Route::get('/checkout',  [CheckoutController::class, 'show'])->name('checkout.show');
        Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');

        Route::get('/account/orders',          [AccountController::class, 'orders'])->name('account.orders.index');
        Route::get('/account/orders/{order}',  [AccountController::class, 'showOrder'])->name('account.orders.show');

        // OPay payment
        Route::post('/account/orders/{order}/pay',       [OPayController::class, 'initiate'])->name('opay.initiate');
        Route::post('/account/orders/{order}/pay/query', [OPayController::class, 'query'])->name('opay.query');

        // Refund requests
        Route::post('/account/orders/{order}/refund', [ShopRefundController::class, 'store'])->name('refund.store');
        Route::post('/account/orders/{order}/refund/{refundRequest}/evidence', [ShopRefundController::class, 'uploadEvidence'])->name('refund.evidence');
        Route::post('/account/orders/{order}/refund/{refundRequest}/cancel', [ShopRefundController::class, 'cancel'])->name('refund.cancel');

        Route::get('/account/profile', [AccountController::class, 'profile'])->name('account.profile');
        Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');

        Route::post('/account/addresses', [AccountController::class, 'storeAddress'])->name('account.addresses.store');
        Route::put('/account/addresses/{address}', [AccountController::class, 'updateAddress'])->name('account.addresses.update');
        Route::delete('/account/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('account.addresses.destroy');
        Route::post('/account/addresses/{address}/default', [AccountController::class, 'setDefaultAddress'])->name('account.addresses.default');

        Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->name('products.reviews.store');
    });
});

// OPay webhook — no auth, no CSRF (exempted in bootstrap/app.php)
Route::post('/opay/webhook', [OPayController::class, 'webhook'])->name('opay.webhook');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
        
        // Password reset
        Route::get('/forgot-password',  [AdminPasswordResetController::class, 'showForgotForm'])->name('password.request');
        Route::post('/forgot-password', [AdminPasswordResetController::class, 'sendResetLink'])->name('password.email');
        Route::get('/reset-password/{token}', [AdminPasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password', [AdminPasswordResetController::class, 'resetPassword'])->name('password.update');
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

        Route::get('return-policy', [AdminReturnPolicyController::class, 'edit'])->name('return-policy.edit');
        Route::put('return-policy', [AdminReturnPolicyController::class, 'update'])->name('return-policy.update');

        Route::get('privacy-policy', [AdminPrivacyPolicyController::class, 'edit'])->name('privacy-policy.edit');
        Route::put('privacy-policy', [AdminPrivacyPolicyController::class, 'update'])->name('privacy-policy.update');

        Route::resource('products', ProductController::class)->middleware('permission:manage_products');
        Route::post('products/{product}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage'])
            ->name('products.images.primary')->middleware('permission:manage_products');

        // Product variants (nested store; shallow update/delete by variant id)
        Route::post('products/{product}/variants',     [ProductVariantController::class, 'store'])
            ->name('products.variants.store')->middleware('permission:manage_products');
        Route::get('variants/{variant}',               [ProductVariantController::class, 'show'])
            ->name('variants.show')->middleware('permission:manage_products');
        Route::put('variants/{variant}',               [ProductVariantController::class, 'update'])
            ->name('variants.update')->middleware('permission:manage_products');
        Route::delete('variants/{variant}',            [ProductVariantController::class, 'destroy'])
            ->name('variants.destroy')->middleware('permission:manage_products');

        Route::resource('categories', CategoryController::class)->except(['show'])->middleware('permission:manage_categories');

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index')->middleware('permission:view_inventory');
        Route::patch('inventory/{inventory}/reorder-level', [InventoryController::class, 'updateReorderLevel'])
            ->name('inventory.reorder')->middleware('permission:update_inventory');
        Route::post('inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])
            ->name('inventory.adjust')->middleware('permission:update_inventory');

        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])->middleware('permission:update_inventory');
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive')->middleware('permission:update_inventory');
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel')->middleware('permission:update_inventory');

        Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show'])->middleware('permission:manage_orders');
        Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->name('orders.mark-paid')->middleware('permission:update_order_status');
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm')->middleware('permission:update_order_status');
        Route::post('orders/{order}/pending-confirmation', [OrderController::class, 'pendingConfirmation'])->name('orders.pending-confirmation')->middleware('permission:update_order_status');
        Route::post('orders/{order}/processing', [OrderController::class, 'processing'])->name('orders.processing')->middleware('permission:update_order_status');
        Route::post('orders/{order}/ship', [OrderController::class, 'ship'])->name('orders.ship')->middleware('permission:update_order_status');
        Route::post('orders/{order}/shipping-to-station', [OrderController::class, 'shippingToStation'])->name('orders.shipping-to-station')->middleware('permission:update_order_status');
        Route::post('orders/{order}/ready-for-pickup', [OrderController::class, 'readyForPickup'])->name('orders.ready-for-pickup')->middleware('permission:update_order_status');
        Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver')->middleware('permission:update_order_status');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel')->middleware('permission:update_order_status');
        Route::post('orders/{order}/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status')->middleware('permission:update_order_status');

        Route::post('orders/{order}/payments', [OrderController::class, 'storePayment'])->name('orders.payments.store')->middleware('permission:manage_orders');
        Route::patch('orders/{order}/delivery-date', [OrderController::class, 'updateDeliveryDate'])->name('orders.delivery-date.update')->middleware('permission:manage_orders');
        Route::patch('orders/{order}/courier',        [OrderController::class, 'updateCourier'])->name('orders.courier.update')->middleware('permission:manage_orders');

        Route::resource('users', UserController::class)->except(['destroy'])->middleware('permission:manage_customers');
        Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])
            ->name('users.assign-role')->middleware('permission:manage_customers');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active')->middleware('permission:manage_customers');
        Route::post('users/{user}/toggle-2fa', [UserController::class, 'toggle2FA'])
            ->name('users.toggle-2fa')->middleware('permission:manage_customers');
        Route::post('users/{user}/generate-backup-code', [UserController::class, 'generate2FABackup'])
            ->name('users.generate-backup-code')->middleware('permission:manage_customers');
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

        Route::resource('pickup-stations', PickupStationController::class)
            ->except(['show'])->middleware('permission:manage_settings');
        Route::get('settings', [App\Http\Controllers\Admin\SettingsController::class, 'edit'])->name('settings.edit')->middleware('permission:manage_settings');
        Route::put('settings', [App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update')->middleware('permission:manage_settings');
        Route::post('pickup-stations/apply-shipping-fee', [PickupStationController::class, 'applyShippingFeeAll'])->name('pickup-stations.apply-shipping-fee')->middleware('permission:manage_settings');
        Route::resource('bank-accounts', App\Http\Controllers\Admin\BankAccountController::class)->only(['index','store','update','destroy'])->middleware('permission:manage_settings');
        Route::get('pickup-payouts', [App\Http\Controllers\Admin\PickupPayoutController::class, 'index'])->name('pickup-payouts.index');
        Route::get('pickup-payouts/records', [App\Http\Controllers\Admin\PickupPayoutController::class, 'records'])->name('pickup-payouts.records');
        Route::get('pickup-payouts/records/export', [App\Http\Controllers\Admin\PickupPayoutController::class, 'export'])->name('pickup-payouts.export');
        Route::get('pickup-payouts/{pickupStation}', [App\Http\Controllers\Admin\PickupPayoutController::class, 'show'])->name('pickup-payouts.show');
        Route::post('pickup-payouts/{pickupStation}/mark-paid', [App\Http\Controllers\Admin\PickupPayoutController::class, 'markPaid'])->name('pickup-payouts.mark-paid');
        Route::post('pickup-payouts/{pickupPayout}/reverse', [App\Http\Controllers\Admin\PickupPayoutController::class, 'reverse'])->name('pickup-payouts.reverse');
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::resource('payment-methods', App\Http\Controllers\Admin\PaymentMethodController::class)->only(['index','update']);
        Route::get('pickup-stations/{pickupStation}/payouts', [PickupStationController::class, 'payouts'])
            ->name('pickup-stations.payouts');

        // Station management routes
        Route::post('pickup-stations/{pickupStation}/toggle-availability', [PickupStationController::class, 'toggleAvailability'])
            ->name('pickup-stations.toggle-availability')->middleware('permission:manage_settings');
        Route::post('pickup-stations/{pickupStation}/set-unavailable', [PickupStationController::class, 'setUnavailable'])
            ->name('pickup-stations.set-unavailable')->middleware('permission:manage_settings');
        Route::get('pickup-stations/{pickupStation}/items', [PickupStationController::class, 'items'])
            ->name('pickup-stations.items')->middleware('permission:manage_settings');
        Route::post('orders/{order}/reassign-station', [PickupStationController::class, 'reassignOrder'])
            ->name('orders.reassign-station')->middleware('permission:manage_settings');

        Route::get('refunds',                                  [AdminRefundController::class, 'index'])->name('refunds.index');
        Route::get('refunds/{refundRequest}',                  [AdminRefundController::class, 'show'])->name('refunds.show');
        Route::post('refunds/{refundRequest}/request-evidence',[AdminRefundController::class, 'requestEvidence'])->name('refunds.request-evidence');
        Route::post('refunds/{refundRequest}/approve',         [AdminRefundController::class, 'approve'])->name('refunds.approve');
        Route::post('refunds/{refundRequest}/reject',          [AdminRefundController::class, 'reject'])->name('refunds.reject');
        Route::post('refunds/{refundRequest}/mark-received',   [AdminRefundController::class, 'markReceived'])->name('refunds.mark-received');
        Route::post('refunds/{refundRequest}/inspect',         [AdminRefundController::class, 'inspect'])->name('refunds.inspect');
        Route::post('refunds/{refundRequest}/process-refund',  [AdminRefundController::class, 'processRefund'])->name('refunds.process-refund');
    });
});

/*
|--------------------------------------------------------------------------
| Pickup Station Staff Portal (PIN-based, no admin auth required)
|--------------------------------------------------------------------------
*/
Route::prefix('pickup-portal')->name('pickup-portal.')->group(function () {
    // Public portal routes (no auth required)
    Route::get('/',        [PickupPortalController::class, 'showLogin'])->name('login');
    Route::post('/login',  [PickupPortalController::class, 'login'])->name('login.post');
    Route::post('/logout', [PickupPortalController::class, 'logout'])->name('logout');

    // Protected portal routes — require portal PIN session
    Route::middleware('auth.portal')->group(function () {
        Route::get('/dashboard',       [PickupPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/data',  [PickupPortalController::class, 'dashboardData'])->name('dashboard.data');
        Route::get('/payments',        [PickupPortalController::class, 'payments'])->name('payments');
        Route::get('/deliveries',      [PickupPortalController::class, 'deliveries'])->name('deliveries');
        Route::get('/payouts',         [PickupPortalController::class, 'payouts'])->name('payouts');
        Route::get('/payouts/data',    [PickupPortalController::class, 'payoutsData'])->name('payouts.data');
        Route::post('/payouts/mark-paid',                    [PickupPortalController::class, 'markPaid'])->name('payouts.markPaid');
        Route::post('/orders/{order}/confirm',               [PickupPortalController::class, 'confirmPickup'])->name('confirm');
        Route::post('/orders/{order}/initiate-payment',      [PickupPortalController::class, 'initiatePayment'])->name('initiate-payment');
        Route::post('/orders/{order}/query-payment',         [PickupPortalController::class, 'queryPayment'])->name('query-payment');
        Route::post('/orders/{order}/record-payment',        [PickupPortalController::class, 'recordPayment'])->name('record-payment');

        // Item-level pickup status routes
        Route::post('/items/{item}/received', [PickupPortalController::class, 'markReceived'])->name('items.received');
        Route::post('/items/{item}/ready',    [PickupPortalController::class, 'markReady'])->name('items.ready');
        Route::post('/items/{item}/picked-up', [PickupPortalController::class, 'markPickedUp'])->name('items.picked-up');

        // Bulk actions
        Route::post('/bulk/received', [PickupPortalController::class, 'bulkMarkReceived'])->name('bulk.received');
        Route::post('/bulk/ready',    [PickupPortalController::class, 'bulkMarkReady'])->name('bulk.ready');

        // Picked up DataTable + export
        Route::get('/picked-up/data', [PickupPortalController::class, 'pickedUpData'])->name('picked-up.data');
        Route::get('/picked-up/export', [PickupPortalController::class, 'pickedUpExport'])->name('picked-up.export');

        // Return collection routes
        Route::get('/returns/{refundRequest}', [PickupPortalController::class, 'returnDetails'])->name('returns.show');
        Route::post('/returns/{refundRequest}/collect', [PickupPortalController::class, 'collectReturn'])->name('returns.collect');
    });
});
