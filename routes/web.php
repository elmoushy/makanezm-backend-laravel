<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Debug Route - Check if Authorization Header is Received
|--------------------------------------------------------------------------
| Visit: https://flyingarrow-backend.com/debug-auth
| This shows if the Authorization header is being passed to PHP
*/
Route::get('/debug-auth', function () {
    $headers = [];

    // Check all possible locations for Authorization header
    $headers['HTTP_AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT SET';
    $headers['REDIRECT_HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'NOT SET';
    $headers['Authorization (getallheaders)'] = function_exists('getallheaders')
        ? (getallheaders()['Authorization'] ?? getallheaders()['authorization'] ?? 'NOT SET')
        : 'getallheaders() not available';
    $headers['Authorization (apache_request_headers)'] = function_exists('apache_request_headers')
        ? (apache_request_headers()['Authorization'] ?? apache_request_headers()['authorization'] ?? 'NOT SET')
        : 'apache_request_headers() not available';
    $headers['PHP_AUTH_USER'] = $_SERVER['PHP_AUTH_USER'] ?? 'NOT SET';
    $headers['Request Header (Laravel)'] = request()->header('Authorization') ?? 'NOT SET';

    return response()->json([
        'message' => 'Authorization Header Debug',
        'headers_found' => $headers,
        'all_server_vars_with_auth' => array_filter($_SERVER, function($key) {
            return stripos($key, 'auth') !== false || stripos($key, 'bearer') !== false;
        }, ARRAY_FILTER_USE_KEY),
        'php_sapi' => php_sapi_name(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    ]);
});
