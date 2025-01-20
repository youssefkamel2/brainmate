<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\welcomeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ModelTestController;
use App\Http\Controllers\PersonalNoteController;

// API Version Prefix for Versioning
Route::prefix('v1')->group(function () {
    
    // Route::get('/', [WelcomeController::class, 'welcome']);


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

            Route::get('test-models', [ModelTestController::class, 'testModels']);

        // project Routes
        Route::prefix('projects')->group(function () {
            Route::post('/create', [Projectcontroller::class, 'createProject']);
            Route::get('/assigned', [ProjectController::class, 'getUserProjects']); 
            Route::get('/{projectId}/teams', [ProjectController::class, 'getProjectTeams']);
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

        // [Personal Notes]

        Route::prefix('notes')->group(function () {
            // Folder routes
            Route::prefix('folders')->group(function () {
                Route::get('/', [FolderController::class, 'index']); 
                Route::post('/', [FolderController::class, 'create']); 
                Route::get('/{folder}', [FolderController::class, 'show']); 
                Route::put('/{folder}', [FolderController::class, 'update']); 
                Route::delete('/{folder}', [FolderController::class, 'delete']); 
                Route::get('/trash/notes', [TrashController::class, 'index']); 
            });
        
            // Favorite routes
            Route::prefix('favorites')->group(function () {
                Route::get('/', [FavoriteController::class, 'index']); 
                Route::post('/', [FavoriteController::class, 'create']); 
                Route::delete('/{id}', [FavoriteController::class, 'delete']); 
            });
        
            // Trash routes
            Route::prefix('trash')->group(function () {
                Route::post('/{id}/restore', [TrashController::class, 'restore']); 
                Route::delete('/{id}', [TrashController::class, 'delete']); 
            });
        
            // PersonalNote routes
            Route::get('/', [PersonalNoteController::class, 'index']); 
            Route::get('/folders/{folder_id}/notes', [PersonalNoteController::class, 'getNotesByFolder']); 
            Route::post('/', [PersonalNoteController::class, 'create']); 
            Route::get('/{id}', [PersonalNoteController::class, 'show']); 
            Route::put('/{id}', [PersonalNoteController::class, 'update']); 
            Route::delete('/{id}', [PersonalNoteController::class, 'delete']); 
        });


    });
});

