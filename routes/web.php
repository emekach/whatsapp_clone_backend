<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return response()->json([
        'status'  => 401,
        'message' => 'Unauthorized. This is a private API.',
        'hint'    => 'Download the mobile app to access this service.',
    ], 401);
});

// Catch all other web routes
Route::fallback(function () {
    return response()->json([
        'status'  => 404,
        'message' => 'Route not found.',
    ], 404);
});
