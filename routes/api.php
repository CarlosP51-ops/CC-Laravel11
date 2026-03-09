<?php


use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Clients\CartController;
use App\Http\Controllers\Clients\HomeController;
use App\Http\Controllers\Clients\OrderController;
use App\Http\Controllers\Clients\PaymentController;
use App\Http\Controllers\Clients\ProductController;
use App\Http\Controllers\Clients\PromotionController;
use App\Http\Controllers\Clients\ReviewController;
use App\Http\Controllers\Clients\WishlistController;
use App\Http\Controllers\Vendors\DashboardController as VendorDashboardController;
use App\Http\Controllers\Vendors\ProductController as VendorProductController;
use App\Http\Controllers\Vendors\OrderController as VendorOrderController;
use App\Http\Controllers\Vendors\PaymentController as VendorPaymentController;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'forgot']);
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.reset');;

// Homepage Routes
Route::prefix('home')->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/categories', [HomeController::class, 'categories']);
    Route::post('/newsletter', [HomeController::class, 'subscribeNewsletter']);
});

// EmptyCart Routes (public)
Route::get('/categories/popular', [HomeController::class, 'popularCategories']);
Route::get('/products/trending', [HomeController::class, 'trendingProducts']);

// Product Routes (public)
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/slug/{slug}', [ProductController::class, 'showBySlug']); // Route par slug (plus sécurisée)
    Route::get('/{id}', [ProductController::class, 'show']); // Route par ID (pour compatibilité)
    Route::get('/{id}/related', [ProductController::class, 'related']);
    Route::get('/{product}/reviews', [ReviewController::class, 'index']);
});

// Promotion Routes (public)
Route::prefix('promotions')->group(function () {
    Route::get('/', [PromotionController::class, 'index']);
    Route::get('/top-deals', [PromotionController::class, 'topDeals']);
});

// Routes protégées par token
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::get('/user/stats', [AuthController::class, 'getStats']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Vérification d'email (si activée)
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail']);
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');

    // Review Routes (protected)
    Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);
    Route::post('/reviews/{review}/helpful', [ReviewController::class, 'markHelpful']);
    Route::post('/reviews/{review}/report', [ReviewController::class, 'report']);

    // Cart Routes (protected)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::put('/items/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
        Route::post('/apply-coupon', [CartController::class, 'applyCoupon']);
    });

    // Order Routes (protected)
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{id}', [OrderController::class, 'show']);
    });

    // Payment Routes (protected)
    Route::prefix('payments')->group(function () {
        Route::get('/history', [PaymentController::class, 'getPaymentHistory']);
        Route::get('/stats', [PaymentController::class, 'getPaymentStats']);
    });

    // Wishlist Routes (protected)
    Route::prefix('wishlist')->group(function () {
        Route::get('/', [WishlistController::class, 'index']);
        Route::post('/', [WishlistController::class, 'store']);
        Route::delete('/{id}', [WishlistController::class, 'destroy']);
        Route::post('/delete-multiple', [WishlistController::class, 'destroyMultiple']);
        Route::get('/check/{productId}', [WishlistController::class, 'check']);
    });

    // Vendor Routes (protected - role: vendor)
    Route::middleware('role:vendor')->prefix('vendor')->group(function () {
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [VendorDashboardController::class, 'getStats']);
            Route::get('/revenue-chart', [VendorDashboardController::class, 'getRevenueChart']);
            Route::get('/recent-orders', [VendorDashboardController::class, 'getRecentOrders']);
            Route::get('/top-products', [VendorDashboardController::class, 'getTopProducts']);
        });

        // Product Management Routes
        Route::prefix('products')->group(function () {
            Route::get('/categories', [VendorProductController::class, 'getCategories']);
            Route::get('/', [VendorProductController::class, 'index']);
            Route::post('/', [VendorProductController::class, 'store']);
            Route::get('/{id}', [VendorProductController::class, 'show']);
            Route::put('/{id}', [VendorProductController::class, 'update']);
            Route::delete('/{id}', [VendorProductController::class, 'destroy']);
            Route::patch('/{id}/toggle-status', [VendorProductController::class, 'toggleStatus']);
        });

        // Order Management Routes
        Route::prefix('orders')->group(function () {
            Route::get('/', [VendorOrderController::class, 'index']);
            Route::get('/{id}', [VendorOrderController::class, 'show']);
            Route::patch('/{id}/status', [VendorOrderController::class, 'updateStatus']);
            Route::patch('/{id}/tracking', [VendorOrderController::class, 'updateTracking']);
            Route::post('/{id}/notes', [VendorOrderController::class, 'addNote']);
        });

        // Payment Management Routes
        Route::prefix('payments')->group(function () {
            Route::get('/stats', [VendorPaymentController::class, 'stats']);
            Route::get('/transactions', [VendorPaymentController::class, 'transactions']);
            Route::post('/withdrawal', [VendorPaymentController::class, 'requestWithdrawal']);
            Route::get('/methods', [VendorPaymentController::class, 'paymentMethods']);
        });
    });
});