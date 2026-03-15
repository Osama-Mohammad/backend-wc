<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

/**
 * Public Storefront (slugs)
 */
Route::prefix('store')->group(function () {
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category:slug}', [CategoryController::class, 'show']);
    Route::get('categories/{category:slug}/products', [CategoryController::class, 'products']);

    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product:slug}', [ProductController::class, 'show']);
});

/**
 * Admin (IDs) - protected
 */
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::apiResource('categories', CategoryController::class)->except(['create', 'edit']);
    Route::apiResource('products', ProductController::class)->except(['create', 'edit']);
});
