<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Pendefinisian named rate limiters untuk middleware `throttle:<name>`.
 *
 * Ambang diselaraskan dengan limiter di frontend SPA (lib/rate-limit.ts)
 * agar perilaku API konsisten dengan aplikasi aslinya.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Default untuk endpoint GET publik / storefront.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Signup / login — dibatasi kuat untuk mencegah brute-force.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip().'|'.$request->input('email', ''));
        });

        // Resend verifikasi / forgot & reset password.
        RateLimiter::for('auth-slow', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email', ''));
        });

        // Operasi CRUD admin (charm, category, cleanup).
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Transaksi store (kasir, check-stock).
        RateLimiter::for('store', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Order create / update / payment.
        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Akun (profile update).
        RateLimiter::for('account', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('account-password', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
