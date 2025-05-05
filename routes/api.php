<?php

use App\Http\Controllers\Api\ActivityLogsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserPermissionsController;
use App\Http\Controllers\Api\UserRolesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailTestController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::get('testing', function () {
    return response()->json(['message' => 'testing indeed']);
});

// ==============================  Publicly accessible index routes ======================================

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

//========== email testing ===================================
Route::post('test-email', [EmailTestController::class, 'testEmail'])->middleware('optional_auth');
Route::post('test-get-user-by-email', [EmailTestController::class, 'getUserWithRolesByEmail'])->middleware('optional_auth');

//====================  Web forgot password reset =================================
Route::post('forgot-password', [PasswordResetController::class, 'forgetPassword']);
Route::get('/reset-password', [PasswordResetController::class, 'handleresetPasswordLoad']);
Route::post('/reset-password', [PasswordResetController::class, 'handlestoringNewPassword']);

//=============================== private routes ==================================
Route::group(
    ['middleware' => ['auth:sanctum']],
    function () {

        //================== users ====================================
        Route::resource('users', UserController::class);
        Route::post('bulk-destroy-users', [UserController::class, 'bulkDestroy']);
        Route::post('/postToUpdateUserProfile', [UserController::class, 'updateUserProfile'])->name('updateUserProfile');

        //=================== system logs =================================================
        Route::get('activity-logs', [ActivityLogsController::class, 'index']);
        Route::post('bulk-destroy-activity-logs', [ActivityLogsController::class, 'bulkDestroy']);

        Route::get('get-logged-in-user', [AuthController::class, 'checkLoginStatus']);
        Route::post('logout', [AuthController::class, 'logout']);

        //Roles AND Permisions
        Route::get('/roles', [UserRolesController::class, 'getAssignableRoles']);

        // Sync permision to roles
        Route::get('roles-with-modified-permissions', [UserRolesController::class, 'getRolesWithModifiedPermissions']);
        Route::post('sync-permissions-to-role', [UserRolesController::class, 'syncPermissionsToRole']);
        Route::Resource('users-roles', UserRolesController::class);
        Route::Resource('users-permissions', UserPermissionsController::class);

    }
);