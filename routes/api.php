<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CategoryController;
use App\Models\Category;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

// Produkt-Routen
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/category/{id}', [ProductController::class, 'byCategory']);

Route::middleware('auth:sanctum')->post('/products', [ProductController::class, 'store']);
Route::middleware('auth:sanctum')->patch('/products/{id}', [ProductController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/products/{id}', [ProductController::class, 'destroy']);

// Routen für den Warenkorb
Route::get('/cart', [CartController::class, 'show']);
Route::post('/cart/items', [CartController::class, 'addItem']);
Route::patch('/cart/items/{cartItemId}', [CartController::class, 'updateItem']);
Route::delete('/cart/items/{cartItemId}', [CartController::class, 'removeItem']);
Route::post('cart/clear', [CartController::class, 'clearCart']);
Route::post('/cart/sync', [CartController::class, 'sync']);
Route::middleware('auth:sanctum')->post('/cart/syncCartOnLogin', [CartController::class, 'syncCartOnLogin']);

// Stripe Routes
Route::middleware('auth:sanctum')->post('/checkout', [CartController::class, 'checkout']);
Route::post('/checkout/success', [CartController::class, 'success']);

//Order Routes
Route::middleware('auth:sanctum')->get('/orders', [OrderController::class, 'index']);

//Category Routes
Route::middleware('auth:sanctum')->post('/category', [CategoryController::class, 'store']);
Route::middleware('auth:sanctum')->get('/category', [CategoryController::class, 'index']);
Route::middleware('auth:sanctum')->delete('/category/{id}', [CategoryController::class, 'delete']);
