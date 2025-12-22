<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // EMERGENCY FIX: Recover Authorization Header
        // 1. Check for standard cPanel redirected headers
        $auth = $_SERVER['HTTP_AUTHORIZATION'] 
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
            ?? $_SERVER['HTTP_X_AUTHORIZATION'] // Custom Header Catch
            ?? $_SERVER['HTTP_X_AUTH_TOKEN']    // Custom Header Catch
            ?? null;

        // 2. Fallback: Check Query Parameter (optional, mainly for testing/embedding)
        if (! $auth && isset($_GET['auth_token'])) {
            $auth = 'Bearer ' . $_GET['auth_token'];
        }

        // 3. Force set if found
        if ($auth) {
            // Ensure It starts with Bearer
            if (! str_starts_with($auth, 'Bearer ')) {
                $auth = 'Bearer ' . $auth;
            }
            
            $_SERVER['HTTP_AUTHORIZATION'] = $auth;
            request()->headers->set('Authorization', $auth);
        }
    }
}
