<?php


use App\Http\Controllers\Clients\FollowController;
use App\Http\Controllers\Clients\ClientNotificationController;
use App\Http\Controllers\Clients\SellerProfileController;
use App\Http\Controllers\ConversationController;
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
use App\Http\Controllers\Vendors\ClientController as VendorClientController;
use App\Http\Controllers\Vendors\ReportController as VendorReportController;
use App\Http\Controllers\Vendors\MessageController as VendorMessageController;
use App\Http\Controllers\Vendors\NotificationController as VendorNotificationController;
use App\Http\Controllers\Admins\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admins\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admins\UserController as AdminUserController;
use App\Http\Controllers\Admins\SellerController as AdminSellerController;
use App\Http\Controllers\Admins\ProductController as AdminProductController;
use App\Http\Controllers\Admins\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admins\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admins\PerformanceController as AdminPerformanceController;
use App\Http\Controllers\Admins\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admins\MessageController as AdminMessageController;
use App\Http\Controllers\Admins\ReportController as AdminReportController;
use App\Http\Controllers\Admins\NotificationController as AdminNotificationController;
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

// Seller public profile (public)
Route::get('/sellers/{id}/public', [SellerProfileController::class, 'show']);

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

    // Follow / Notifications client
    Route::post('/sellers/{id}/follow', [FollowController::class, 'toggle']);
    Route::get('/sellers/{id}/follow', [FollowController::class, 'check']);
    Route::prefix('notifications')->group(function () {
        Route::get('/', [ClientNotificationController::class, 'index']);
        Route::get('/unread-count', [ClientNotificationController::class, 'unreadCount']);
        Route::post('/read-all', [ClientNotificationController::class, 'markAllRead']);
        Route::patch('/{id}/read', [ClientNotificationController::class, 'markRead']);
        Route::delete('/{id}', [ClientNotificationController::class, 'destroy']);
    });

    // ─── CONVERSATIONS (partagé tous rôles) ──────────────────────────────────
    Route::prefix('conversations')->group(function () {
        Route::get('/stats', [ConversationController::class, 'stats']);
        Route::get('/', [ConversationController::class, 'index']);
        Route::get('/users', [ConversationController::class, 'searchUsers']);
        Route::post('/find-or-create', [ConversationController::class, 'findOrCreate']);
        Route::get('/{id}/thread', [ConversationController::class, 'thread']);
        Route::post('/{id}/send', [ConversationController::class, 'send']);
        Route::delete('/{id}', [ConversationController::class, 'destroy']);
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
            Route::get('/{id}/stats', [VendorProductController::class, 'stats']);
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

        // Client Management Routes
        Route::prefix('clients')->group(function () {
            Route::get('/', [VendorClientController::class, 'index']);
            Route::get('/stats', [VendorClientController::class, 'stats']);
            Route::post('/newsletter', [VendorClientController::class, 'sendNewsletter']);
            Route::get('/{id}', [VendorClientController::class, 'show']);
        });

        // Report Routes
        Route::prefix('reports')->group(function () {
            Route::get('/overview', [VendorReportController::class, 'overview']);
            Route::get('/sales-chart', [VendorReportController::class, 'salesChart']);
            Route::get('/top-products', [VendorReportController::class, 'topProducts']);
            Route::get('/category-stats', [VendorReportController::class, 'categoryStats']);
            Route::get('/conversion-stats', [VendorReportController::class, 'conversionStats']);
            Route::post('/export', [VendorReportController::class, 'export']);
        });

        // Messages (avec l'admin)
        Route::prefix('messages')->group(function () {
            Route::get('/stats', [VendorMessageController::class, 'stats']);
            Route::get('/thread', [VendorMessageController::class, 'thread']);
            Route::post('/send', [VendorMessageController::class, 'send']);
        });

        // Notifications vendeur
        Route::prefix('notifications')->group(function () {
            Route::get('/', [VendorNotificationController::class, 'index']);
            Route::get('/unread-count', [VendorNotificationController::class, 'unreadCount']);
            Route::post('/read-all', [VendorNotificationController::class, 'markAllRead']);
            Route::patch('/{id}/read', [VendorNotificationController::class, 'markRead']);
            Route::delete('/{id}', [VendorNotificationController::class, 'destroy']);
        });
    });

    // Admin Routes (protected - role: admin)
    Route::middleware(['role:admin', 'throttle:120,1'])->prefix('admin')->group(function () {
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [AdminDashboardController::class, 'getStats']);
            Route::get('/revenue-chart', [AdminDashboardController::class, 'getRevenueChart']);
            Route::get('/top-sellers', [AdminDashboardController::class, 'getTopSellers']);
            Route::get('/recent-activity', [AdminDashboardController::class, 'getRecentActivity']);
        });

        // Users
        Route::get('/users/stats', [AdminUserController::class, 'stats']);
        Route::post('/users/bulk-delete', [AdminUserController::class, 'bulkDelete']);
        Route::post('/users/bulk-toggle-status', [AdminUserController::class, 'bulkToggleStatus']);
        Route::get('/users/export', [AdminUserController::class, 'export']);
        Route::patch('/users/{id}/restore', [AdminUserController::class, 'restore']);
        Route::apiResource('users', AdminUserController::class);
        Route::patch('/users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);

        // Sellers
        Route::get('/sellers/stats', [AdminSellerController::class, 'stats']);
        Route::get('/sellers/export', [AdminSellerController::class, 'export']);
        Route::patch('/sellers/{id}/toggle-status', [AdminSellerController::class, 'toggleStatus']);
        Route::patch('/sellers/{id}/verify', [AdminSellerController::class, 'verify']);
        Route::post('/sellers/{id}/restrictions', [AdminSellerController::class, 'applyRestriction']);
        Route::delete('/sellers/{id}/restrictions/{restrictionId}', [AdminSellerController::class, 'removeRestriction']);
        Route::get('/sellers/{id}/products', [AdminSellerController::class, 'sellerProducts']);
        Route::get('/sellers/{id}/orders', [AdminSellerController::class, 'sellerOrders']);
        Route::apiResource('sellers', AdminSellerController::class)->only(['index', 'show']);

        // Products
        Route::get('/products/stats', [AdminProductController::class, 'stats']);
        Route::get('/products/export', [AdminProductController::class, 'export']);
        Route::get('/products/categories', [AdminProductController::class, 'categories']);
        Route::post('/products/bulk-moderate', [AdminProductController::class, 'bulkModerate']);
        Route::post('/products/bulk-delete', [AdminProductController::class, 'bulkDelete']);
        Route::patch('/products/{id}/moderate', [AdminProductController::class, 'moderate']);
        Route::patch('/products/{id}/toggle-status', [AdminProductController::class, 'toggleStatus']);
        Route::apiResource('products', AdminProductController::class)->only(['index', 'show', 'destroy']);

        // Categories
        Route::get('/categories/stats', [AdminCategoryController::class, 'stats']);
        Route::get('/categories/tree', [AdminCategoryController::class, 'tree']);
        Route::patch('/categories/{id}/toggle-status', [AdminCategoryController::class, 'toggleStatus']);
        Route::apiResource('categories', AdminCategoryController::class);

        // Performance
        Route::prefix('performance')->group(function () {
            Route::get('/overview', [AdminPerformanceController::class, 'overview']);
            Route::get('/sellers', [AdminPerformanceController::class, 'sellers']);
            Route::get('/products', [AdminPerformanceController::class, 'products']);
            Route::get('/alerts', [AdminPerformanceController::class, 'alerts']);
        });

        // Promotions
        Route::prefix('promotions')->group(function () {
            Route::get('/stats', [AdminPromotionController::class, 'stats']);
            Route::get('/', [AdminPromotionController::class, 'index']);
            Route::patch('/{id}/toggle', [AdminPromotionController::class, 'toggle']);
            Route::patch('/{id}/price', [AdminPromotionController::class, 'updatePrice']);
            Route::post('/bulk-toggle', [AdminPromotionController::class, 'bulkToggle']);
        });

        // Messages
        Route::prefix('messages')->group(function () {
            Route::get('/stats', [AdminMessageController::class, 'stats']);
            Route::get('/conversations', [AdminMessageController::class, 'conversations']);
            Route::get('/users', [AdminMessageController::class, 'users']);
            Route::get('/thread/{userId}', [AdminMessageController::class, 'thread']);
            Route::post('/send', [AdminMessageController::class, 'send']);
            Route::patch('/read/{userId}', [AdminMessageController::class, 'markRead']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/stats', [AdminReportController::class, 'stats']);
            Route::get('/overview', [AdminReportController::class, 'overview']);
            Route::get('/sales', [AdminReportController::class, 'sales']);
            Route::get('/users', [AdminReportController::class, 'users']);
            Route::get('/export', [AdminReportController::class, 'export']);
        });

        // Notifications admin
        Route::prefix('notifications')->group(function () {
            Route::get('/', [AdminNotificationController::class, 'index']);
            Route::get('/stats', [AdminNotificationController::class, 'stats']);
            Route::patch('/{id}/read', [AdminNotificationController::class, 'markRead']);
            Route::post('/read-all', [AdminNotificationController::class, 'markAllRead']);
            Route::delete('/clear-read', [AdminNotificationController::class, 'clearRead']);
            Route::delete('/{id}', [AdminNotificationController::class, 'destroy']);
        });

        // Newsletter admin
        Route::prefix('newsletter')->group(function () {
            Route::post('/send', [AdminNewsletterController::class, 'send']);
            Route::get('/preview', [AdminNewsletterController::class, 'preview']);
        });

        // Payments
        Route::get('/payments/stats', [AdminPaymentController::class, 'stats']);
        Route::get('/payments/withdrawals', [AdminPaymentController::class, 'withdrawals']);
        Route::patch('/payments/withdrawals/{id}/approve', [AdminPaymentController::class, 'approveWithdrawal']);
        Route::patch('/payments/withdrawals/{id}/reject', [AdminPaymentController::class, 'rejectWithdrawal']);
        Route::get('/payments/transactions', [AdminPaymentController::class, 'payments']);
        Route::get('/payments/gateways', [AdminPaymentController::class, 'gateways']);
        Route::patch('/payments/gateways/{slug}', [AdminPaymentController::class, 'updateGateway']);
        Route::get('/payments/export', [AdminPaymentController::class, 'export']);
    });
});