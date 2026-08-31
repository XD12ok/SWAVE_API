<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CharmController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\KasirController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth (public)
    Route::post('auth/signup', [SignupController::class, 'store']);
    Route::post('auth/login', [LoginController::class, 'store']);
    Route::post('auth/logout', [LogoutController::class, 'destroy']);
    Route::get('auth/me', [LoginController::class, 'me']);
    Route::get('auth/verify', [VerifyEmailController::class, 'show']);
    Route::post('auth/resend-verification', [ResendVerificationController::class, 'store']);
    Route::post('auth/forgot-password', [ForgotPasswordController::class, 'store']);
    Route::post('auth/reset-password', [ResetPasswordController::class, 'store']);

    // Public (read / storefront) — matches original SPA behaviour
    Route::get('catalog', [CatalogController::class, 'index']);
    Route::get('charms', [CharmController::class, 'index']);
    Route::get('charms/{id}', [CharmController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::get('orders/{id}/payment', [PaymentController::class, 'show']);
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/logs', [InventoryController::class, 'logs']);
    Route::get('admin/alerts', [AdminController::class, 'index']);
    Route::get('shipping-cost', [ShippingController::class, 'show']);
    Route::get('events', [EventController::class, 'index']);
    Route::post('check-stock', [InventoryController::class, 'checkStock']);
    Route::post('kasir', [KasirController::class, 'store']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::patch('orders/{id}', [OrderController::class, 'update']);
    Route::patch('orders/{id}/payment', [PaymentController::class, 'update']);
    Route::post('charms', [CharmController::class, 'store']);
    Route::patch('charms/{id}', [CharmController::class, 'update']);
    Route::delete('charms/{id}', [CharmController::class, 'destroy']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::patch('categories/{id}', [CategoryController::class, 'update']);
    Route::delete('categories/{id}', [CategoryController::class, 'destroy']);
    Route::post('inventory/cleanup', [InventoryController::class, 'cleanup']);
    Route::post('upload', [UploadController::class, 'store']);

    // Authenticated (account only) — demonstrates auth middleware
    Route::middleware('auth.session')->group(function () {
        Route::patch('account/profile', [AccountController::class, 'updateProfile']);
        Route::put('account/password', [AccountController::class, 'updatePassword']);
        Route::get('account/orders', [AccountController::class, 'orders']);
    });
});
