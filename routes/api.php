<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\welcomeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ModelTestController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonalNoteController;

// API Version Prefix for Versioning
Route::prefix('v1')->group(function () {

    // Route::get('/', [WelcomeController::class, 'welcome']);

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


        Route::prefix('home')->group(function () {
            Route::get('/to-do-list', [HomeController::class, 'getToDoList']);
            Route::get('/task-statistics', [HomeController::class, 'getTaskStatistics']);
            Route::get('/in-review', [HomeController::class, 'getInReviewTasks']);
        });

        Route::prefix('user')->group(function () {
            Route::get('/', [AuthController::class, 'user']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        // project, teams Routes
        Route::prefix('projects')->group(function () {
            Route::get('/assigned', [ProjectController::class, 'getUserProjects']);
            Route::get('/{projectId}', [ProjectController::class, 'getProjectDetails']);
            Route::post('/create', [Projectcontroller::class, 'createProject']);
            Route::put('/{projectId}', [ProjectController::class, 'updateProject']);
            Route::delete('/{projectId}', [ProjectController::class, 'deleteProject']);

            // teams
            Route::post('/{projectId}/teams/create', [TeamController::class, 'createTeam']);
            Route::put('/teams/{teamId}', [TeamController::class, 'updateTeam']);
            Route::delete('/teams/{teamId}', [TeamController::class, 'deleteTeam']);
            Route::post('/teams/{teamId}/invite', [TeamController::class, 'inviteUserToTeam']);
            Route::post('/teams/accept', [TeamController::class, 'acceptInvitation']);
            Route::post('/teams/reject', [TeamController::class, 'rejectInvitation']);
            Route::post('/teams/join', [TeamController::class, 'joinTeam']);
            Route::get('/teams/{teamId}', [TeamController::class, 'getTeamDetails']);
            Route::get('/{projectId}/teams', [TeamController::class, 'listTeamsInProject']);
            Route::delete('/teams/{teamId}/remove-user', [TeamController::class, 'removeUserFromTeam']);
            Route::put('/teams/{teamId}/change-role', [TeamController::class, 'changeUserRole']);
            Route::post('/teams/{teamId}/leave', [TeamController::class, 'leaveTeam']);
            Route::get('/teams/get/my-teams', [TeamController::class, 'getMyTeams']);
            Route::get('/teams/{teamId}/users', [TeamController::class, 'getTeamUsers']);
        });

        // Task 
        Route::prefix('tasks')->group(function () {
            // Route::get('/assigned', [TaskController::class, 'getAssignedTasks']);
            Route::get('/{task}', [TaskController::class, 'getTaskById']);
            Route::post('/', [TaskController::class, 'createTask']); // Create task
            Route::put('/{taskId}', [TaskController::class, 'updateTask']); // Update task
            Route::delete('/{taskId}', [TaskController::class, 'deleteTask']); // Delete task
            Route::get('/teams/{teamId}/tasks', [TaskController::class, 'getTeamTasks']); // Get team tasks
            Route::get('/', [TaskController::class, 'getAllTasks']); // Get all tasks (assigned to user or teams they belong to)
            Route::post('/{taskId}/notes', [TaskController::class, 'addTaskNote']); // Add task note
            Route::put('/{taskId}/status', [TaskController::class, 'updateTaskStatus']); // update state
            Route::post('/{taskId}/attachments', [TaskController::class, 'addAttachments']); // Add attachments to a task
            Route::delete('/attachments/{attachmentId}', [TaskController::class, 'removeAttachment']); // Remove an attachment from a task
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
                // based on requirement from stupid front end
                Route::get('/trash/notes', [TrashController::class, 'index']);
                Route::get('/favorites/notes', [FavoriteController::class, 'index']);
            });

            // Favorite routes
            Route::prefix('favorites')->group(function () {
                Route::post('/', [FavoriteController::class, 'toggleFavorite']);
            });

            // Trash routes
            Route::prefix('trash')->group(function () {
                Route::delete('/deleteAll', [TrashController::class, 'deleteAll']);
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

        // worksapces
        Route::prefix('workspaces')->group(function () {
            Route::get('/', [WorkspaceController::class, 'index']);
            Route::post('/', [WorkspaceController::class, 'create']);
        });

        // profile
        Route::prefix('profile')->group(function () {
            Route::get('/', [UserController::class, 'getProfile']);
            Route::put('/', [UserController::class, 'updateProfile']);
            Route::put('/password', [UserController::class, 'updatePassword']);
        });

        // chat
        Route::prefix('chat')->group(function () {
            Route::get('/teams', [ChatController::class, 'getChatTeams']);
            Route::get('/messages/{teamId}', [ChatController::class, 'getMessages']);
            Route::post('/send', [ChatController::class, 'sendMessage']);
            Route::delete('/messages/{messageId}', [ChatController::class, 'deleteMessage']);
        });

        Route::prefix('materials')->group(function () {
            Route::get('/{teamId}', [MaterialController::class, 'index']); // Get materials and task attachments
            Route::post('/{teamId}', [MaterialController::class, 'store']); // Upload material
            Route::delete('/{attachmentId}', [MaterialController::class, 'destroy']); // Delete material or attachment
        });

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']); // Get all notifications
            Route::post('/{notificationId}/read', [NotificationController::class, 'markAsRead']); // Mark as read
            Route::delete('/{notificationId}', [NotificationController::class, 'destroy']); // Delete notification
        });
    });
});
