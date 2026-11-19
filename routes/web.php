<?php

use App\Http\Controllers\PayPalController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [PayPalController::class, 'index'])
    ->name('payment.index');

Route::post('/paypal/create-order', [PayPalController::class, 'createOrder'])
    ->name('paypal.create-order');

Route::post('/paypal/capture-order', [PayPalController::class, 'captureOrder'])
    ->name('paypal.capture-order');