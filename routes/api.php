<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\welcomeController;

Route::get('/', [welcomeController::class, 'welcome']);

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware(['web'])->group(function () {
    Route::get('auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
});

Route::post('password/reset-link', [AuthController::class, 'sendResetLink']);
Route::post('password/reset', [AuthController::class, 'reset']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);
});

Route::post('validate-token', [AuthController::class, 'validateToken']); 

