<?php

use App\Http\Controllers\Admin\AboutController as AdminAboutController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BankAccountController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DealController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PasswordResetController as AdminPasswordResetController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PickupPayoutController;
use App\Http\Controllers\Admin\PickupStationController;
use App\Http\Controllers\Admin\PrivacyPolicyController as AdminPrivacyPolicyController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ProfitReportController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ReturnPolicyController as AdminReturnPolicyController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorApprovalController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\PickupPortalController;
use App\Http\Controllers\Shop\AboutController as ShopAboutController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\AuthController as ShopAuthController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\ContactController as ShopContactController;
use App\Http\Controllers\Shop\CustomOrderController;
use App\Http\Controllers\Shop\CustomOrderFileController;
use App\Http\Controllers\Shop\PwaInstallController;
use App\Http\Controllers\Shop\DealController as ShopDealController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\PasswordResetController as ShopPasswordResetController;
use App\Http\Controllers\Shop\PrivacyPolicyController as ShopPrivacyPolicyController;
use App\Http\Controllers\Shop\RefundController as ShopRefundController;
use App\Http\Controllers\Shop\ReturnPolicyController as ShopReturnPolicyController;
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
    Route::get('/cookie-policy', [\App\Http\Controllers\Shop\CookiePolicyController::class, 'show'])->name('cookie-policy');
    Route::post('/contact', [ShopContactController::class, 'send'])->name('contact.send')->middleware('throttle:5,1');

    Route::post('/pwa/install', [PwaInstallController::class, 'store'])->name('pwa.install');
    Route::get('/shop', [ShopController::class, 'index'])->name('products.index');
    Route::get('/products/{product}', [ShopController::class, 'show'])->name('products.show');

    Route::get('/deals', [ShopDealController::class, 'index'])->name('deals.index');
    Route::get('/deals/{deal}', [ShopDealController::class, 'show'])->name('deals.show');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
    Route::post('/cart/{variant}', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{variant}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{variant}', [CartController::class, 'remove'])->name('cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [ShopAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [ShopAuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('/register', [ShopAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [ShopAuthController::class, 'register'])->middleware('throttle:3,1');
        Route::get('/login/2fa', [ShopAuthController::class, 'show2FA'])->name('2fa.show');
        Route::post('/login/2fa', [ShopAuthController::class, 'verify2FA'])->name('2fa.verify')->middleware('throttle:10,1');

        // Password reset
        Route::get('/forgot-password', [ShopPasswordResetController::class, 'showForgotForm'])->name('password.request');
        Route::post('/forgot-password', [ShopPasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:3,1');
        Route::get('/reset-password/{token}', [ShopPasswordResetController::class, 'showResetForm'])->name('password.reset');
        Route::post('/reset-password', [ShopPasswordResetController::class, 'resetPassword'])->name('password.update')->middleware('throttle:5,1');
    });
    Route::post('/logout', [ShopAuthController::class, 'logout'])
        ->middleware('auth')->name('logout');

    // Guest checkout OTP verification (before checkout routes)
    Route::post('/checkout/send-otp', [CheckoutController::class, 'sendOtp'])->name('checkout.send-otp')->middleware('throttle:3,1');
    Route::get('/checkout/verify-otp', [CheckoutController::class, 'showVerifyOtp'])->name('checkout.verify-otp');
    Route::post('/checkout/verify-otp', [CheckoutController::class, 'verifyOtp'])->name('checkout.verify-otp.post');
    Route::post('/checkout/resend-otp', [CheckoutController::class, 'resendOtp'])->name('checkout.resend-otp')->middleware('throttle:3,1');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
    Route::get('/order-lookup', [CheckoutController::class, 'orderLookupForm'])->name('order.lookup');
    Route::post('/order-lookup', [CheckoutController::class, 'orderLookup'])->name('order.lookup.submit');
    Route::get('/order-track/{token}', [CheckoutController::class, 'orderTrack'])->name('order.track');

    // Email verification
    Route::get('/verify-email/{id}/{hash}', [ShopAuthController::class, 'verifyEmail'])
        ->name('verification.verify');
    Route::post('/verify-email/resend', [ShopAuthController::class, 'resendVerification'])
        ->middleware('throttle:3,1')
        ->name('verification.resend');

    Route::middleware('auth')->group(function () {

        Route::get('/account/orders', [AccountController::class, 'orders'])->name('account.orders.index');
        Route::get('/account/orders/{order}', [AccountController::class, 'showOrder'])->name('account.orders.show');
        Route::put('/account/orders/{order}/payment-method', [AccountController::class, 'changePaymentMethod'])->name('account.orders.change-payment-method');

        // Paystack payment
        Route::post('/account/orders/{order}/pay', [PaystackController::class, 'initiate'])->name('paystack.initiate');
        Route::get('/account/orders/{order}/pay/callback', [PaystackController::class, 'callback'])->name('paystack.callback');
        Route::post('/account/orders/{order}/pay/query', [PaystackController::class, 'query'])->name('paystack.query');

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

// Custom Frock — create is public, rest requires auth
Route::get('/custom-frock', [CustomOrderController::class, 'index'])->name('shop.custom-frock.index')->middleware('auth');
Route::get('/custom-frock/create', [CustomOrderController::class, 'create'])->name('shop.custom-frock.create')->middleware('auth');
Route::post('/custom-frock', [CustomOrderController::class, 'store'])->name('shop.custom-frock.store')->middleware(['auth', 'throttle:10,1']);
Route::get('/custom-frock/{customOrder}', [CustomOrderController::class, 'show'])->name('shop.custom-frock.show')->middleware('auth');
Route::post('/custom-frock/{customOrder}/approve-quote', [CustomOrderController::class, 'approveQuote'])->name('shop.custom-frock.approve-quote')->middleware(['auth', 'throttle:5,1']);
Route::get('/custom-frock/{customOrder}/payment', [CustomOrderController::class, 'payment'])->name('shop.custom-frock.payment')->middleware('auth');
Route::post('/custom-frock/{customOrder}/request-changes', [CustomOrderController::class, 'requestChanges'])->name('shop.custom-frock.request-changes')->middleware(['auth', 'throttle:10,1']);
Route::post('/custom-frock/{customOrder}/cancel', [CustomOrderController::class, 'cancel'])->name('shop.custom-frock.cancel')->middleware('auth');
Route::get('/custom-frock/{customOrder}/files/{file}', [CustomOrderFileController::class, 'show'])->name('shop.custom-frock.file')->middleware('auth');

// Guest Paystack payment — uses order lookup token for guest access
Route::post('/order-track/{token}/pay', [PaystackController::class, 'guestInitiate'])->name('shop.paystack.guest-initiate');
Route::get('/order-track/{token}/pay/callback', [PaystackController::class, 'guestCallback'])->name('shop.paystack.guest-callback');
Route::post('/order-track/{token}/pay/query', [PaystackController::class, 'guestQuery'])->name('shop.paystack.guest-query');

// Paystack webhook — no auth, no CSRF (exempted in bootstrap/app.php)
Route::post('/paystack/webhook', [PaystackController::class, 'webhook'])->name('paystack.webhook')->middleware('throttle:60,1');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {

    // Password reset
    Route::get('/forgot-password', [AdminPasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [AdminPasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:3,1');
    Route::get('/reset-password/{token}', [AdminPasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AdminPasswordResetController::class, 'resetPassword'])->name('password.update')->middleware('throttle:5,1');
    // Admin authentication (outside auth middleware)
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('/login/2fa', [AdminAuthController::class, 'showTwoFactorForm'])->name('login.2fa');
        Route::post('/login/2fa', [AdminAuthController::class, 'verifyTwoFactor'])->name('login.verify-2fa')->middleware('throttle:10,1');
    });
    Route::match(['get', 'post'], '/logout', [AdminAuthController::class, 'logout'])
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
        Route::post('contact/messages/{message}/spam', [AdminContactController::class, 'markAsSpam'])->name('contact.messages.spam');
        Route::post('contact/messages/{message}/replied', [AdminContactController::class, 'markAsReplied'])->name('contact.messages.replied');
        Route::post('contact/messages/{message}/archive', [AdminContactController::class, 'archive'])->name('contact.messages.archive');
        Route::delete('contact/messages/{message}', [AdminContactController::class, 'destroyMessage'])->name('contact.messages.destroy');

        Route::get('return-policy', [AdminReturnPolicyController::class, 'edit'])->name('return-policy.edit');
        Route::put('return-policy', [AdminReturnPolicyController::class, 'update'])->name('return-policy.update');

        Route::get('privacy-policy', [AdminPrivacyPolicyController::class, 'edit'])->name('privacy-policy.edit');
        Route::put('privacy-policy', [AdminPrivacyPolicyController::class, 'update'])->name('privacy-policy.update');

        Route::resource('products', ProductController::class)->middleware('permission:manage_products');
        Route::post('products/{product}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage'])
            ->name('products.images.primary')->middleware('permission:manage_products');
        Route::post('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
            ->name('products.toggle-status')->middleware('permission:manage_products');

        // Product variants (nested store; shallow update/delete by variant id)
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])
            ->name('products.variants.store')->middleware('permission:manage_products');
        Route::get('variants/{variant}', [ProductVariantController::class, 'show'])
            ->name('variants.show')->middleware('permission:manage_products');
        Route::put('variants/{variant}', [ProductVariantController::class, 'update'])
            ->name('variants.update')->middleware('permission:manage_products');
        Route::delete('variants/{variant}', [ProductVariantController::class, 'destroy'])
            ->name('variants.destroy')->middleware('permission:manage_products');

        Route::resource('categories', CategoryController::class)->except(['show'])->middleware('permission:manage_categories');

        Route::get('deals', [DealController::class, 'index'])->name('deals.index')->middleware('permission:manage_deals');
        Route::get('deals/create', [DealController::class, 'create'])->name('deals.create')->middleware('permission:manage_deals');
        Route::post('deals', [DealController::class, 'store'])->name('deals.store')->middleware('permission:manage_deals');
        Route::get('deals/{deal}', [DealController::class, 'show'])->name('deals.show')->middleware('permission:manage_deals');
        Route::get('deals/{deal}/edit', [DealController::class, 'edit'])->name('deals.edit')->middleware('permission:manage_deals');
        Route::put('deals/{deal}', [DealController::class, 'update'])->name('deals.update')->middleware('permission:manage_deals');
        Route::post('deals/{deal}/cancel', [DealController::class, 'cancel'])->name('deals.cancel')->middleware('permission:manage_deals');
        Route::post('deals/{deal}/duplicate', [DealController::class, 'duplicate'])->name('deals.duplicate')->middleware('permission:manage_deals');
        Route::delete('deals/{deal}', [DealController::class, 'destroy'])->name('deals.destroy')->middleware('permission:manage_deals');

        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index')->middleware('permission:manage_coupons');
        Route::get('coupons/create', [CouponController::class, 'create'])->name('coupons.create')->middleware('permission:manage_coupons');
        Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store')->middleware('permission:manage_coupons');
        Route::get('coupons/{coupon}', [CouponController::class, 'show'])->name('coupons.show')->middleware('permission:manage_coupons');
        Route::get('coupons/{coupon}/edit', [CouponController::class, 'edit'])->name('coupons.edit')->middleware('permission:manage_coupons');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update')->middleware('permission:manage_coupons');
        Route::post('coupons/{coupon}/activate', [CouponController::class, 'activate'])->name('coupons.activate')->middleware('permission:manage_coupons');
        Route::post('coupons/{coupon}/deactivate', [CouponController::class, 'deactivate'])->name('coupons.deactivate')->middleware('permission:manage_coupons');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy')->middleware('permission:manage_coupons');

        // Custom Orders
        Route::get('custom-orders', [App\Http\Controllers\Admin\CustomOrderController::class, 'index'])->name('custom-orders.index')->middleware('permission:manage_orders');
        Route::get('custom-orders/{customOrder}', [App\Http\Controllers\Admin\CustomOrderController::class, 'show'])->name('custom-orders.show')->middleware('permission:manage_orders');

        // PWA Installs
        Route::get('pwa-installs', [App\Http\Controllers\Admin\PwaInstallController::class, 'index'])->name('pwa-installs.index');

        // Pickup Reports
        Route::get('pickup-reports', [App\Http\Controllers\Admin\PickupReportController::class, 'index'])->name('pickup-reports.index');
        Route::get('pickup-reports/{report}', [App\Http\Controllers\Admin\PickupReportController::class, 'show'])->name('pickup-reports.show');
        Route::put('pickup-reports/{report}', [App\Http\Controllers\Admin\PickupReportController::class, 'update'])->name('pickup-reports.update');

        // Image optimization status
        Route::get('image-status', [App\Http\Controllers\Admin\ImageStatusController::class, 'index'])->name('image-status.index');
        Route::post('custom-orders/{customOrder}/review', [App\Http\Controllers\Admin\CustomOrderController::class, 'review'])->name('custom-orders.review')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/request-info', [App\Http\Controllers\Admin\CustomOrderController::class, 'requestInfo'])->name('custom-orders.request-info')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/approve-for-quote', [App\Http\Controllers\Admin\CustomOrderController::class, 'approveForQuote'])->name('custom-orders.approve-for-quote')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/reject', [App\Http\Controllers\Admin\CustomOrderController::class, 'reject'])->name('custom-orders.reject')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/quote', [App\Http\Controllers\Admin\CustomOrderController::class, 'storeQuote'])->name('custom-orders.quote')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/start-production', [App\Http\Controllers\Admin\CustomOrderController::class, 'startProduction'])->name('custom-orders.start-production')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/submit-for-qc', [App\Http\Controllers\Admin\CustomOrderController::class, 'submitForQc'])->name('custom-orders.submit-for-qc')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/quality-check', [App\Http\Controllers\Admin\CustomOrderController::class, 'qualityCheck'])->name('custom-orders.quality-check')->middleware('permission:manage_orders');
        Route::patch('custom-orders/{customOrder}/qc-checks/{check}', [App\Http\Controllers\Admin\CustomOrderController::class, 'updateQcCheck'])->name('custom-orders.qc-check.update')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/mark-ready', [App\Http\Controllers\Admin\CustomOrderController::class, 'markReady'])->name('custom-orders.mark-ready')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/mark-shipped', [App\Http\Controllers\Admin\CustomOrderController::class, 'markShipped'])->name('custom-orders.mark-shipped')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/complete', [App\Http\Controllers\Admin\CustomOrderController::class, 'complete'])->name('custom-orders.complete')->middleware('permission:manage_orders');
        Route::post('custom-orders/{customOrder}/message', [App\Http\Controllers\Admin\CustomOrderController::class, 'sendMessage'])->name('custom-orders.message')->middleware(['permission:manage_orders', 'throttle:10,1']);
        Route::patch('custom-orders/{customOrder}/notes', [App\Http\Controllers\Admin\CustomOrderController::class, 'updateNotes'])->name('custom-orders.update-notes')->middleware('permission:manage_orders');
        Route::get('custom-orders/{customOrder}/files/{file}', [App\Http\Controllers\Admin\CustomOrderController::class, 'serveFile'])->name('custom-orders.file')->middleware('permission:manage_orders');

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
        Route::post('orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment'])->name('orders.confirm-payment')->middleware('permission:update_order_status');
        Route::post('orders/{order}/reject-payment', [OrderController::class, 'rejectPayment'])->name('orders.reject-payment')->middleware('permission:update_order_status');
        Route::post('orders/{order}/confirm-under-review', [OrderController::class, 'confirmUnderReview'])->name('orders.confirm-under-review')->middleware('permission:update_order_status');
        Route::post('orders/{order}/reject-under-review', [OrderController::class, 'rejectUnderReview'])->name('orders.reject-under-review')->middleware('permission:update_order_status');

        Route::post('orders/{order}/payments', [OrderController::class, 'storePayment'])->name('orders.payments.store')->middleware('permission:manage_orders');
        Route::patch('orders/{order}/delivery-date', [OrderController::class, 'updateDeliveryDate'])->name('orders.delivery-date.update')->middleware('permission:manage_orders');
        Route::patch('orders/{order}/courier', [OrderController::class, 'updateCourier'])->name('orders.courier.update')->middleware('permission:manage_orders');

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
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit')->middleware('permission:manage_settings');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update')->middleware('permission:manage_settings');
        Route::post('pickup-stations/apply-shipping-fee', [PickupStationController::class, 'applyShippingFeeAll'])->name('pickup-stations.apply-shipping-fee')->middleware('permission:manage_settings');
        Route::resource('bank-accounts', BankAccountController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:manage_settings');
        Route::get('pickup-payouts', [PickupPayoutController::class, 'index'])->name('pickup-payouts.index')->middleware('permission:manage_settings');
        Route::get('pickup-payouts/records', [PickupPayoutController::class, 'records'])->name('pickup-payouts.records')->middleware('permission:manage_settings');
        Route::get('pickup-payouts/records/export', [PickupPayoutController::class, 'export'])->name('pickup-payouts.export')->middleware('permission:manage_settings');
        Route::get('pickup-payouts/{pickupStation}', [PickupPayoutController::class, 'show'])->name('pickup-payouts.show')->middleware('permission:manage_settings');
        Route::get('pickup-payouts/{pickupStation}/data', [PickupPayoutController::class, 'showData'])->name('pickup-payouts.show-data')->middleware('permission:manage_settings');
        Route::post('pickup-payouts/{pickupStation}/mark-paid', [PickupPayoutController::class, 'markPaid'])->name('pickup-payouts.mark-paid')->middleware('permission:manage_settings');
        Route::post('pickup-payouts/{pickupPayout}/reverse', [PickupPayoutController::class, 'reverse'])->name('pickup-payouts.reverse')->middleware('permission:manage_settings');
        Route::resource('suppliers', SupplierController::class)->except(['show'])->middleware('permission:manage_inventory');
        Route::resource('payment-methods', PaymentMethodController::class)->only(['index', 'update'])->middleware('permission:manage_settings');
        Route::get('pickup-stations/{pickupStation}/payouts', [PickupStationController::class, 'payouts'])
            ->name('pickup-stations.payouts')->middleware('permission:manage_settings');

        // Station management routes
        Route::post('pickup-stations/{pickupStation}/toggle-availability', [PickupStationController::class, 'toggleAvailability'])
            ->name('pickup-stations.toggle-availability')->middleware('permission:manage_settings');
        Route::post('pickup-stations/{pickupStation}/set-unavailable', [PickupStationController::class, 'setUnavailable'])
            ->name('pickup-stations.set-unavailable')->middleware('permission:manage_settings');
        Route::get('pickup-stations/{pickupStation}/items', [PickupStationController::class, 'items'])
            ->name('pickup-stations.items')->middleware('permission:manage_settings');
        Route::get('pickup-stations/{pickupStation}/items/data', [PickupStationController::class, 'itemsData'])
            ->name('pickup-stations.items.data')->middleware('permission:manage_settings');
        Route::post('orders/{order}/reassign-station', [PickupStationController::class, 'reassignOrder'])
            ->name('orders.reassign-station')->middleware('permission:manage_settings');

        Route::get('refunds', [AdminRefundController::class, 'index'])->name('refunds.index')->middleware('permission:manage_orders');
        Route::get('refunds/{refundRequest}', [AdminRefundController::class, 'show'])->name('refunds.show')->middleware('permission:manage_orders');
        Route::post('refunds/{refundRequest}/request-evidence', [AdminRefundController::class, 'requestEvidence'])->name('refunds.request-evidence')->middleware('permission:manage_orders');
        Route::post('refunds/{refundRequest}/approve', [AdminRefundController::class, 'approve'])->name('refunds.approve')->middleware('permission:manage_orders');
        Route::post('refunds/{refundRequest}/reject', [AdminRefundController::class, 'reject'])->name('refunds.reject')->middleware('permission:manage_orders');
        Route::post('refunds/{refundRequest}/mark-received', [AdminRefundController::class, 'markReceived'])->name('refunds.mark-received')->middleware('permission:manage_orders');
        Route::post('refunds/{refundRequest}/inspect', [AdminRefundController::class, 'inspect'])->name('refunds.inspect')->middleware('permission:manage_orders');
        Route::post('refunds/{refundRequest}/process-refund', [AdminRefundController::class, 'processRefund'])->name('refunds.process-refund')->middleware('permission:manage_orders');
        Route::post('refunds/{refundRequest}/mark-replacement-shipped', [AdminRefundController::class, 'markReplacementShipped'])->name('refunds.mark-replacement-shipped')->middleware('permission:manage_orders');
        Route::get('refunds/{refundRequest}/evidence', [AdminRefundController::class, 'evidence'])->name('refunds.evidence')->middleware('permission:manage_orders');
        Route::get('refunds/{refundRequest}/evidence-video', [AdminRefundController::class, 'evidenceVideo'])->name('refunds.evidence-video')->middleware('permission:manage_orders');
    });
});

/*
|--------------------------------------------------------------------------
| Pickup Station Staff Portal (PIN-based, no admin auth required)
|--------------------------------------------------------------------------
*/
Route::prefix('pickup-portal')->name('pickup-portal.')->group(function () {
    // Public portal routes (no auth required)
    Route::get('/', [PickupPortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [PickupPortalController::class, 'login'])->name('login.post');
    Route::post('/logout', [PickupPortalController::class, 'logout'])->name('logout');

    // Protected portal routes — require portal PIN session
    Route::middleware('auth.portal')->group(function () {
        Route::get('/dashboard', [PickupPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard/data', [PickupPortalController::class, 'dashboardData'])->name('dashboard.data');
        Route::get('/payments', [PickupPortalController::class, 'payments'])->name('payments');
        Route::get('/deliveries', [PickupPortalController::class, 'deliveries'])->name('deliveries');
        Route::get('/payouts', [PickupPortalController::class, 'payouts'])->name('payouts');
        Route::get('/payouts/data', [PickupPortalController::class, 'payoutsData'])->name('payouts.data');
        Route::post('/payouts/mark-paid', [PickupPortalController::class, 'markPaid'])->name('payouts.markPaid');
        Route::post('/orders/{order}/confirm', [PickupPortalController::class, 'confirmPickup'])->name('confirm');
        Route::post('/orders/{order}/initiate-payment', [PickupPortalController::class, 'initiatePayment'])->name('initiate-payment');
        Route::post('/orders/{order}/query-payment', [PickupPortalController::class, 'queryPayment'])->name('query-payment');
        Route::post('/orders/{order}/record-payment', [PickupPortalController::class, 'recordPayment'])->name('record-payment');

        // Item-level pickup status routes
        Route::post('/items/{item}/received', [PickupPortalController::class, 'markReceived'])->name('items.received');
        Route::post('/items/{item}/ready', [PickupPortalController::class, 'markReady'])->name('items.ready');
        Route::post('/items/{item}/picked-up', [PickupPortalController::class, 'markPickedUp'])->name('items.picked-up');

        // Bulk actions
        Route::post('/bulk/received', [PickupPortalController::class, 'bulkMarkReceived'])->name('bulk.received');
        Route::post('/bulk/ready', [PickupPortalController::class, 'bulkMarkReady'])->name('bulk.ready');

        // Picked up DataTable + export
        Route::get('/picked-up/data', [PickupPortalController::class, 'pickedUpData'])->name('picked-up.data');
        Route::get('/picked-up/export', [PickupPortalController::class, 'pickedUpExport'])->name('picked-up.export');

        // Return collection routes
        Route::get('/returns/{refundRequest}', [PickupPortalController::class, 'returnDetails'])->name('returns.show');
        Route::post('/returns/{refundRequest}/collect', [PickupPortalController::class, 'collectReturn'])->name('returns.collect');

        // Reminder routes
        Route::post('/orders/{order}/send-reminder', [PickupPortalController::class, 'sendReminder'])->name('send-reminder')->middleware('throttle:3,1');
        Route::post('/returns/{return}/send-reminder', [PickupPortalController::class, 'sendReturnReminder'])->name('send-return-reminder')->middleware('throttle:3,1');

        // Payment verification
        Route::post('/orders/{order}/submit-payment', [PickupPortalController::class, 'submitPaymentVerification'])->name('submit-payment');
        Route::get('/orders/{order}/payment-status', [PickupPortalController::class, 'paymentStatus'])->name('payment-status');

        // Reports
        Route::get('/reports', [PickupPortalController::class, 'reports'])->name('reports');
        Route::get('/reports/data', [PickupPortalController::class, 'reportsData'])->name('reports.data');
        Route::get('/reports/create', [PickupPortalController::class, 'createReport'])->name('reports.create');
        Route::post('/reports', [PickupPortalController::class, 'storeReport'])->name('reports.store')->middleware('throttle:10,1');
    });
});
