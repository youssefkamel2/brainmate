<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\welcomeController;
use App\Http\Controllers\ModelTestController;


// API Version Prefix for Versioning
Route::prefix('v1')->group(function () {
    
    Route::get('/', [WelcomeController::class, 'welcome']);

    // Public Routes
    Route::get('test-models', [ModelTestController::class, 'testModels']);

    // Authentication Routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('validate-token', [AuthController::class, 'validateToken']);

        Route::middleware(['web'])->group(function () {
            Route::get('google', [AuthController::class, 'redirectToGoogle']);
            Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);
        });
    });

    // Password Reset Routes
    Route::prefix('password')->group(function () {
        // token
        Route::post('reset/request', [AuthController::class, 'sendResetLink']);
        Route::post('reset/confirm', [AuthController::class, 'reset']);
        // code
        Route::post('reset/app/email', [AuthController::class, 'sendResetCode']);
        Route::post('reset/app/code', [AuthController::class, 'verifyResetCode']);
        Route::post('reset/app/confirm', [AuthController::class, 'resetPasswordApp']);

    });

    // Protected Routes (Require API Authentication)
    Route::middleware('auth:api')->group(function () {
        Route::prefix('user')->group(function () {
            Route::get('/', [AuthController::class, 'user']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        // Task Routes
        Route::prefix('tasks')->group(function () {
            Route::get('/', [TaskController::class, 'getAllTasks']); 
            Route::get('/assigned', [TaskController::class, 'getAssignedTasks']);
            Route::get('/{task}', [TaskController::class, 'getTaskById']);
            Route::post('/', [TaskController::class, 'createTask']); 
            Route::put('/{task}', [TaskController::class, 'updateTask']); 
            Route::delete('/{task}', [TaskController::class, 'deleteTask']); 
        });

    });
});

