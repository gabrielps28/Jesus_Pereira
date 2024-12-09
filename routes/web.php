<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DepositController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/deposit', [DepositController::class, 'deposit']);
Route::post('/webhook/superwalletz', [DepositController::class, 'webhookSuperWalletz'])->name('webhook.superwalletz');