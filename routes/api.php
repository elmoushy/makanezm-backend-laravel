<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Base URL: /api/v1
|
| Note: A User with role 'USER' IS a Customer - they are the same entity.
|
*/

Route::prefix('v1')->group(function () {

    // Load authentication routes
    require __DIR__.'/auth.php';

    // Load user routes (includes profile management for customers)
    require __DIR__.'/user.php';

    // Load company routes (resale companies management)
    require __DIR__.'/company.php';

    // Load product routes (products, payment options, resale plans)
    require __DIR__.'/product.php';

    // Load cart routes (shopping cart management)
    require __DIR__.'/cart.php';

    // Load wallet routes (wallet management, deposit, withdraw)
    require __DIR__.'/wallet.php';

    // Load order routes (place orders, sale/resale management)
    require __DIR__.'/order.php';

    // Load dashboard routes (stats, partnerships, deferred sales)
    require __DIR__.'/dashboard.php';

    // Load marquee routes (banner text management)
    require __DIR__.'/marquee.php';

    // Load slider routes (hero slider management)
    require __DIR__.'/slider.php';

    // Load hero routes (hero section content management)
    require __DIR__.'/hero.php';

    // Load investment payout routes (admin payout management)
    require __DIR__.'/investment-payout.php';

    // Load user investment routes (user investment tracking)
    require __DIR__.'/investment.php';

    // Load contact message routes (contact form submissions)
    require __DIR__.'/contact.php';

    // Load discount code routes (coupon management)
    require __DIR__.'/discount.php';

    // Load payment routes (MyFatoorah integration)
    require __DIR__.'/payment.php';

    // Load footer link routes (social media links)
    require __DIR__.'/footer.php';

    // DEBUG: Inspect Headers (Remove in production)
    Route::get('/debug-headers', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'headers' => $request->headers->all(),
            'server_auth' => [
                'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
                'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
            ],
        ]);
    });
});
