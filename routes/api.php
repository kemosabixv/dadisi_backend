<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;

Route::prefix('auth')->group(function () {
	Route::post('signup', [AuthController::class, 'signup'])->name('auth.signup');
	Route::post('login', [AuthController::class, 'login'])->name('auth.login');
	Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('auth.logout');
	Route::get('user', [AuthController::class, 'getAuthenticatedUser'])->middleware('auth:sanctum')->name('auth.user');

	Route::post('/password/email', [AuthController::class, 'sendPasswordResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');
	Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/password/change', [AuthController::class, 'changePassword'])->middleware('auth:sanctum')->name('password.change');

	// Email verification routes
	Route::post('send-verification', [EmailVerificationController::class, 'send'])
		->middleware(['auth:sanctum', 'throttle:6,1'])
		->name('auth.send-verification');
	Route::post('verify-email', [EmailVerificationController::class, 'verify'])
		->name('auth.verify-email');
});

// Member Profile routes (authenticated)
use App\Http\Controllers\Api\MemberProfileController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('member-profiles/me', [MemberProfileController::class, 'me']);
    Route::post('member-profiles', [MemberProfileController::class, 'store']);
    Route::get('member-profiles', [MemberProfileController::class, 'index']);
    Route::get('member-profiles/{id}', [MemberProfileController::class, 'show']);
    Route::put('member-profiles/{id}', [MemberProfileController::class, 'update']);
    Route::delete('member-profiles/{id}', [MemberProfileController::class, 'destroy']);
    // Custom route for getting counties
    Route::get('counties', [MemberProfileController::class, 'getCounties'])->name('counties.index');
});

// User Management routes (authenticated)
use App\Http\Controllers\Api\UserController;

Route::middleware('auth:sanctum')->group(function () {
    // Self-service operations (must come before parameterized routes)
    Route::delete('users/self', [UserController::class, 'deleteSelf']);
    Route::get('users/self/export', [UserController::class, 'exportData']);

    // Standard CRUD operations
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::put('users/{id}', [UserController::class, 'update']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    // Admin-only operations
    Route::post('users/{id}/restore', [UserController::class, 'restore']);
    Route::delete('users/{id}/force', [UserController::class, 'forceDelete']);
    Route::get('users/{id}/audit', [UserController::class, 'auditLog']);

    // Role management (Super Admin only)
    Route::post('users/{id}/assign-role', [UserController::class, 'assignRole']);
    Route::post('users/{id}/remove-role', [UserController::class, 'removeRole']);
    Route::post('users/{id}/sync-roles', [UserController::class, 'syncRoles']);

    // Bulk operations (Super Admin only)
    Route::post('users/bulk/assign-role', [UserController::class, 'bulkAssignRole']);
    Route::post('users/bulk/remove-role', [UserController::class, 'bulkRemoveRole']);
    Route::post('users/bulk/delete', [UserController::class, 'bulkDelete']);
    Route::post('users/bulk/restore', [UserController::class, 'bulkRestore']);
    Route::post('users/bulk/update', [UserController::class, 'bulkUpdate']);
});

// Data Retention Management routes (Super Admin only)
use App\Http\Controllers\Api\UserDataRetentionController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('retention-settings', [UserDataRetentionController::class, 'index']);
    Route::get('retention-settings/{retention}', [UserDataRetentionController::class, 'show']);
    Route::put('retention-settings/{retention}', [UserDataRetentionController::class, 'update']);
    Route::get('retention-settings-summary', [UserDataRetentionController::class, 'summary']);
});

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;

// RBAC Management routes (Super Admin only)
Route::middleware('auth:sanctum')->group(function () {
    // Permission Management
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::post('permissions', [PermissionController::class, 'store']);
    Route::get('permissions/{permission}', [PermissionController::class, 'show']);
    Route::put('permissions/{permission}', [PermissionController::class, 'update']);
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy']);

    // Role Management
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);

    // Permission Assignment to Roles
    Route::post('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('roles/{role}/permissions', [RoleController::class, 'removePermissions']);
});

// Additional API routes can be added here
