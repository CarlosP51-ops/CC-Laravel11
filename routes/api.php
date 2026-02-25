<?php


use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Clients\HomeController;
use App\Http\Controllers\Clients\ProductController;
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



// Routes protégées par token
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Vérification d'email (si activée)
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail']);
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
});