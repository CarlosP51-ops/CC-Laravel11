<?php


use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Clients\CartController;
use App\Http\Controllers\Clients\HomeController;
use App\Http\Controllers\Clients\ProductController;
use App\Http\Controllers\Clients\ReviewController;
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
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::get('/{id}/related', [ProductController::class, 'related']);
    Route::get('/{product}/reviews', [ReviewController::class, 'index']);
});

// Routes protégées par token
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
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
});