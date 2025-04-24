<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\PaymentController;

Route::middleware('verify.jwt')->group(function () {
    Route::post('/payments/initiate', [PaymentController::class, 'initiate']);
    Route::post('/payments/simulate', [PaymentController::class, 'simulate']);
    Route::get('/payments', [PaymentController::class, 'index']);
});
