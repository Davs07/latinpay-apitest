<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// Rutas para Orders
Route::get('orders', [OrderController::class, 'index']);
Route::post('orders', [OrderController::class, 'store']);
Route::get('orders/{order}', [OrderController::class, 'show']);

// Rutas para Payments 
Route::post('orders/{order}/payments', [PaymentController::class, 'store']);
Route::get('orders/{order}/payment-attempts', [PaymentController::class, 'attempts']);
Route::get('payments/{id}', [PaymentController::class, 'show']);