<?php

use App\Http\Controllers\API\ActivityLogsController;
use App\Http\Controllers\API\ContactUsController;
use App\Http\Controllers\API\dashboard\RoomStatisticsCardsController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\FeatureController;
use App\Http\Controllers\API\HeroSliderController;
use App\Http\Controllers\API\RoomBookingController;
use App\Http\Controllers\API\RoomCategoryController;
use App\Http\Controllers\API\RoomController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserPermissionsController;
use App\Http\Controllers\API\UserRolesController;
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

// Routes for Room Management
Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);
Route::apiResource('features', FeatureController::class)->only(['index']);

// Routes for Room Category Management
Route::apiResource('room-categories', RoomCategoryController::class)->only(['index', 'show']);

//======= check room availability ============================
Route::post('check-room-availability', [RoomController::class, 'checkAvailability'])->middleware('optional_auth');

Route::Resource('faqs', FaqController::class)->only(['index'])->middleware('optional_auth');

// Contact Us routes
Route::post('contact-us', [ContactUsController::class, 'sendContactUsNotification'])->middleware('optional_auth');

// Routes for Hero Slider Management
Route::apiResource('hero-sliders', HeroSliderController::class)->only(['index', 'show']);

//=============================== private routes ==================================
Route::group(
    ['middleware' => ['auth:sanctum']],
    function () {

        // Routes for Hero Slider Management
        Route::apiResource('hero-sliders', HeroSliderController::class)->except(['index', 'show']);
        Route::post('bulk-destroy-hero-sliders', [HeroSliderController::class, 'bulkDestroy']);

        //======================= faqs =============================
        Route::Resource('faqs', FaqController::class)->except(['index']);
        Route::post('bulk-destroy-faqs', [UserController::class, 'bulkDestroy']);

        // Room dashboard statistics cards
        Route::get('getAllStatisticsCards', [RoomStatisticsCardsController::class, 'getAllStatisticsCards']);

        // Routes for Room Category Management
        Route::apiResource('room-categories', RoomCategoryController::class)->except(['index', 'show']);
        Route::post('bulk-destroy-room-categories', [RoomCategoryController::class, 'bulkDestroy']);

        // Routes for Room booking
        Route::apiResource('room-bookings', RoomBookingController::class);
        Route::post('bulk-destroy-room-bookings', [RoomBookingController::class, 'bulkDestroy']);

        // Routes for Room Management
        Route::apiResource('rooms', RoomController::class)->except(['index', 'show']);
        Route::post('bulk-destroy-rooms', [RoomController::class, 'bulkDestroy']);

        // Routes for Room Management
        Route::apiResource('features', FeatureController::class)->except(['index']);

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
