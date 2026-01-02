<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\Admin\ReconciliationController;
use App\Http\Controllers\Api\PublicDonationController;
use App\Http\Controllers\Api\AuditLogController;

Route::prefix('auth')->group(function () {
	Route::post('signup', [AuthController::class, 'signup'])->middleware('throttle:5,1')->name('auth.signup');
	Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('auth.login');
	Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
	Route::get('user', [AuthController::class, 'getAuthenticatedUser'])->middleware('auth:sanctum')->name('auth.user');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum')->name('auth.me');
    
    // Token refresh for silent token rotation
    Route::post('refresh', [RefreshTokenController::class, 'refresh'])->middleware('auth:sanctum')->name('auth.refresh');

	Route::post('/password/email', [AuthController::class, 'sendPasswordResetLinkEmail'])->middleware('throttle:5,1')->name('password.email');
	Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/password/change', [AuthController::class, 'changePassword'])->middleware('auth:sanctum')->name('password.change');

	// Email verification routes
	Route::post('send-verification', [EmailVerificationController::class, 'send'])
		->middleware(['auth:sanctum', 'throttle:6,1'])
		->name('auth.send-verification');
	Route::post('verify-email', [EmailVerificationController::class, 'verify'])
		->name('auth.verify-email');

    // Two-Factor Authentication - TOTP
    Route::prefix('2fa/totp')->middleware('auth:sanctum')->group(function () {
        Route::post('enable', [TwoFactorController::class, 'enable'])->name('auth.2fa.totp.enable');
        Route::post('verify', [TwoFactorController::class, 'verify'])->name('auth.2fa.totp.verify');
        Route::post('disable', [TwoFactorController::class, 'disable'])->name('auth.2fa.totp.disable');
        Route::post('recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('auth.2fa.totp.recovery-codes');
        Route::post('regenerate-recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('auth.2fa.totp.regenerate');
    });
    // TOTP validation at login (no auth required - user just provided password)
    Route::post('2fa/totp/validate', [TwoFactorController::class, 'validateCode'])
        ->middleware('throttle:5,1')
        ->name('auth.2fa.totp.validate');

    // Passkeys (WebAuthn)
    Route::prefix('passkeys')->group(function () {
        // Registration requires auth
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('register/options', [PasskeyController::class, 'registerOptions'])->name('auth.passkeys.register.options');
            Route::post('register', [PasskeyController::class, 'register'])->name('auth.passkeys.register');
            Route::get('/', [PasskeyController::class, 'index'])->name('auth.passkeys.index');
            Route::delete('{id}', [PasskeyController::class, 'destroy'])->name('auth.passkeys.destroy');
        });
        // Authentication doesn't require existing auth
        Route::post('authenticate/options', [PasskeyController::class, 'authenticateOptions'])
            ->middleware('throttle:10,1')
            ->name('auth.passkeys.authenticate.options');
        Route::post('authenticate', [PasskeyController::class, 'authenticate'])
            ->middleware('throttle:5,1')
            ->name('auth.passkeys.authenticate');
    });
});

// Counties - Public read access (no auth required)
use App\Http\Controllers\Api\CountyController;
Route::get('counties', [CountyController::class, 'index'])->name('counties.index');
Route::get('counties/{county}', [CountyController::class, 'show'])->name('counties.show');

// Counties - Authenticated CUD (policy handles permission check)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('counties', [CountyController::class, 'store'])->name('counties.store');
    Route::put('counties/{county}', [CountyController::class, 'update'])->name('counties.update');
    Route::delete('counties/{county}', [CountyController::class, 'destroy'])->name('counties.destroy');
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
    Route::post('member-profiles/profile-picture', [MemberProfileController::class, 'uploadProfilePicture']);
});

// Notifications routes (authenticated)
use App\Http\Controllers\Api\NotificationController;

Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::post('/clear-all', [NotificationController::class, 'clearAll'])->name('notifications.clear-all');
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

// User Management routes (authenticated)
use App\Http\Controllers\Api\UserController;
// Self-service user endpoints (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    // Self-service operations (must come before parameterized routes)
    Route::delete('users/self', [UserController::class, 'deleteSelf']);
    Route::get('users/self/export', [UserController::class, 'exportData']);
    Route::post('users/self/profile-picture', [UserController::class, 'uploadProfilePicture']);
});

// Admin-protected user management and RBAC operations
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Bulk operations (Super Admin only) - MUST come before parameterized routes
    Route::post('users/bulk/assign-role', [UserController::class, 'bulkAssignRole']);
    Route::post('users/bulk/remove-role', [UserController::class, 'bulkRemoveRole']);
    Route::post('users/bulk/delete', [UserController::class, 'bulkDelete']);
    Route::post('users/bulk/restore', [UserController::class, 'bulkRestore']);
    Route::post('users/bulk/update', [UserController::class, 'bulkUpdate']);
    Route::post('users/invite', [UserController::class, 'invite']);
    Route::post('users/bulk-invite', [UserController::class, 'bulkInvite']);

    // Standard CRUD operations (admin-managed)
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
});

// Data Retention Management routes (Super Admin only)
use App\Http\Controllers\Api\UserDataRetentionController;

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Specific routes before parameterized routes
    Route::get('retention-settings-summary', [UserDataRetentionController::class, 'summary']);
    Route::post('retention-settings/update-days', [UserDataRetentionController::class, 'updateRetentionDays']);

    // Parameterized routes
    Route::get('retention-settings', [UserDataRetentionController::class, 'index']);
    Route::get('retention-settings/{retention}', [UserDataRetentionController::class, 'show']);
    Route::put('retention-settings/{retention}', [UserDataRetentionController::class, 'update']);

    // Scheduler management
    Route::get('schedulers', [UserDataRetentionController::class, 'getSchedulers']);
    Route::post('schedulers/update', [UserDataRetentionController::class, 'updateScheduler']);
});

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AdminMenuController;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Route model bindings: allow lookup by name or ID for permissions and roles
Route::bind('permission', function ($value) {
    if (is_numeric($value)) {
        return Permission::where('id', $value)->firstOrFail();
    }
    return Permission::where('name', $value)->firstOrFail();
});

Route::bind('role', function ($value) {
    if (is_numeric($value)) {
        return Role::where('id', $value)->firstOrFail();
    }
    return Role::where('name', $value)->firstOrFail();
});

// Admin Menu route: restricted to admin users only to avoid discovery of admin surface
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('menu', [AdminMenuController::class, 'index'])->name('admin.menu');
});

// RBAC Management routes (Super Admin only)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Permission Management
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('permissions/{permission}', [PermissionController::class, 'show']);

    // Role Management
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::get('roles/{role}', [RoleController::class, 'show']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::delete('roles/{role}', [RoleController::class, 'destroy']);

    // Permission Assignment to Roles
    Route::post('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('roles/{role}/permissions', [RoleController::class, 'removePermissions']);

    // Global Audit Logs
    Route::get('audit-logs', [UserController::class, 'bulkAuditLogs']);
});

// Plan Management routes
use App\Http\Controllers\Api\PlanController;

// Public: list and view active subscription plans (guest-facing)
Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('plans/{plan}', [PlanController::class, 'show'])->name('plans.show');

// Exchange Rate routes (public read, admin-only write)
Route::get('exchange-rates/latest', [ExchangeRateController::class, 'index'])->name('exchange-rates.latest');
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('admin.exchange-rates.index');
    Route::get('exchange-rates/info', [ExchangeRateController::class, 'show'])->name('admin.exchange-rates.show');
    Route::post('exchange-rates/refresh', [ExchangeRateController::class, 'refresh'])->name('admin.exchange-rates.refresh');
    Route::put('exchange-rates/settings', [ExchangeRateController::class, 'updateCache'])->name('admin.exchange-rates.settings');
    Route::put('exchange-rates/rate', [ExchangeRateController::class, 'updateManual'])->name('admin.exchange-rates.rate');
});

// Admin-only plan management (create/update/delete)
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
});

// Donation Campaign routes
use App\Http\Controllers\Api\PublicDonationCampaignController;
use App\Http\Controllers\Api\DonationCampaignAdminController;

// Public: list and view active donation campaigns
Route::prefix('donation-campaigns')->group(function () {
    Route::get('/', [PublicDonationCampaignController::class, 'index'])->name('donation-campaigns.index');
    Route::get('/{campaign:slug}', [PublicDonationCampaignController::class, 'show'])->name('donation-campaigns.show');
    Route::post('/{campaign:slug}/donate', [PublicDonationCampaignController::class, 'donate'])->name('donation-campaigns.donate');
});

// Admin-only donation campaign management
Route::prefix('admin/donation-campaigns')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/', [DonationCampaignAdminController::class, 'index'])->name('admin.donation-campaigns.index');
    Route::get('/create', [DonationCampaignAdminController::class, 'create'])->name('admin.donation-campaigns.create');
    Route::post('/', [DonationCampaignAdminController::class, 'store'])->name('admin.donation-campaigns.store');
    Route::get('/{campaign:slug}', [DonationCampaignAdminController::class, 'show'])->name('admin.donation-campaigns.show');
    Route::get('/{campaign:slug}/edit', [DonationCampaignAdminController::class, 'edit'])->name('admin.donation-campaigns.edit');
    Route::put('/{campaign:slug}', [DonationCampaignAdminController::class, 'update'])->name('admin.donation-campaigns.update');
    Route::delete('/{campaign:slug}', [DonationCampaignAdminController::class, 'destroy'])->name('admin.donation-campaigns.destroy');
    Route::post('/{campaign:slug}/restore', [DonationCampaignAdminController::class, 'restore'])->name('admin.donation-campaigns.restore');
    Route::post('/{campaign:slug}/publish', [DonationCampaignAdminController::class, 'publish'])->name('admin.donation-campaigns.publish');
    Route::post('/{campaign:slug}/unpublish', [DonationCampaignAdminController::class, 'unpublish'])->name('admin.donation-campaigns.unpublish');
    Route::post('/{campaign:slug}/complete', [DonationCampaignAdminController::class, 'complete'])->name('admin.donation-campaigns.complete');
});

// Donations - Public and User routes

Route::prefix('donations')->group(function () {
    // Public: Get donation by reference (for checkout page)
    Route::get('/ref/{reference}', [PublicDonationController::class, 'show'])->name('donations.show');

    // Authenticated: List user's donations and create new donation
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/', [PublicDonationController::class, 'index'])->name('donations.index');
        Route::post('/', [PublicDonationController::class, 'store'])->name('donations.store');
        Route::delete('/{donation}', [PublicDonationController::class, 'destroy'])->name('donations.destroy');
    });
});

// Admin Donations Management
use App\Http\Controllers\Api\Admin\DonationAdminController;

Route::prefix('admin/donations')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/', [DonationAdminController::class, 'index'])->name('admin.donations.index');
    Route::get('/stats', [DonationAdminController::class, 'stats'])->name('admin.donations.stats');
    Route::get('/{donation}', [DonationAdminController::class, 'show'])->name('admin.donations.show');
});


// Blog Management - Public routes (read-only)
use App\Http\Controllers\Api\PublicPostController;

Route::prefix('blog')->group(function () {
    Route::get('posts', [PublicPostController::class, 'index'])->name('blog.posts.index');
    Route::get('posts/{post:slug}', [PublicPostController::class, 'show'])->name('blog.posts.show');
    Route::get('categories', [PublicPostController::class, 'categories'])->name('blog.categories.index');
    Route::get('tags', [PublicPostController::class, 'tags'])->name('blog.tags.index');
});

// Blog Management - Admin routes (CRUD)
use App\Http\Controllers\Api\PostAdminController;
use App\Http\Controllers\Api\CategoryAdminController;
use App\Http\Controllers\Api\TagAdminController;

Route::prefix('admin/blog')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Posts management - using slug as primary identifier
    Route::get('posts', [PostAdminController::class, 'index'])->name('admin.blog.posts.index');
    Route::get('posts/create', [PostAdminController::class, 'create'])->name('admin.blog.posts.create');
    Route::post('posts', [PostAdminController::class, 'store'])->name('admin.blog.posts.store');
    Route::get('posts/{post:slug}', [PostAdminController::class, 'show'])->name('admin.blog.posts.show');
    Route::get('posts/{post:slug}/edit', [PostAdminController::class, 'edit'])->name('admin.blog.posts.edit');
    Route::put('posts/{post:slug}', [PostAdminController::class, 'update'])->name('admin.blog.posts.update');
    Route::delete('posts/{post:slug}', [PostAdminController::class, 'destroy'])->name('admin.blog.posts.destroy');
    Route::post('posts/{post:slug}/restore', [PostAdminController::class, 'restore'])->name('admin.blog.posts.restore');
    Route::delete('posts/{post:slug}/force', [PostAdminController::class, 'forceDelete'])->name('admin.blog.posts.forceDelete');
    Route::post('posts/{post:slug}/publish', [PostAdminController::class, 'publish'])->name('admin.blog.posts.publish');
    Route::post('posts/{post:slug}/unpublish', [PostAdminController::class, 'unpublish'])->name('admin.blog.posts.unpublish');


    // Categories management - explicit routes
    Route::get('categories', [CategoryAdminController::class, 'index'])->name('admin.blog.categories.index');
    Route::get('categories/create', [CategoryAdminController::class, 'create'])->name('admin.blog.categories.create');
    Route::post('categories', [CategoryAdminController::class, 'store'])->name('admin.blog.categories.store');
    Route::get('categories/{category}', [CategoryAdminController::class, 'show'])->name('admin.blog.categories.show');
    Route::get('categories/{category}/edit', [CategoryAdminController::class, 'edit'])->name('admin.blog.categories.edit');
    Route::put('categories/{category}', [CategoryAdminController::class, 'update'])->name('admin.blog.categories.update');
    Route::delete('categories/{category}', [CategoryAdminController::class, 'destroy'])->name('admin.blog.categories.destroy');

    // Tags management - explicit routes
    Route::get('tags', [TagAdminController::class, 'index'])->name('admin.blog.tags.index');
    Route::get('tags/create', [TagAdminController::class, 'create'])->name('admin.blog.tags.create');
    Route::post('tags', [TagAdminController::class, 'store'])->name('admin.blog.tags.store');
    Route::get('tags/{tag}', [TagAdminController::class, 'show'])->name('admin.blog.tags.show');
    Route::get('tags/{tag}/edit', [TagAdminController::class, 'edit'])->name('admin.blog.tags.edit');
    Route::put('tags/{tag}', [TagAdminController::class, 'update'])->name('admin.blog.tags.update');
    Route::delete('tags/{tag}', [TagAdminController::class, 'destroy'])->name('admin.blog.tags.destroy');

    // Deletion Reviews (staff only)
    Route::get('deletion-reviews', [\App\Http\Controllers\Api\DeletionReviewController::class, 'index'])->name('admin.blog.deletion-reviews.index');
    Route::post('deletion-reviews/{type}/{id}/approve', [\App\Http\Controllers\Api\DeletionReviewController::class, 'approve'])->name('admin.blog.deletion-reviews.approve');
    Route::post('deletion-reviews/{type}/{id}/reject', [\App\Http\Controllers\Api\DeletionReviewController::class, 'reject'])->name('admin.blog.deletion-reviews.reject');
});

// Author Blog Management - user's own categories and tags with deletion request workflow
use App\Http\Controllers\Api\AuthorBlogController;
use App\Http\Controllers\Api\AuthorPostController;

Route::prefix('user/blog')->middleware('auth:sanctum')->group(function () {
    // Posts (author's own) - using slug as primary identifier
    Route::get('posts', [AuthorPostController::class, 'index'])->name('user.blog.posts.index');
    Route::get('posts/create', [AuthorPostController::class, 'create'])->name('user.blog.posts.create');
    Route::post('posts', [AuthorPostController::class, 'store'])->name('user.blog.posts.store');
    Route::get('posts/{post:slug}', [AuthorPostController::class, 'show'])->name('user.blog.posts.show');
    Route::get('posts/{post:slug}/edit', [AuthorPostController::class, 'edit'])->name('user.blog.posts.edit');
    Route::put('posts/{post:slug}', [AuthorPostController::class, 'update'])->name('user.blog.posts.update');
    Route::delete('posts/{post:slug}', [AuthorPostController::class, 'destroy'])->name('user.blog.posts.destroy');
    Route::post('posts/{post:slug}/restore', [AuthorPostController::class, 'restore'])->name('user.blog.posts.restore');
    Route::post('posts/{post:slug}/publish', [AuthorPostController::class, 'publish'])->name('user.blog.posts.publish');
    Route::post('posts/{post:slug}/unpublish', [AuthorPostController::class, 'unpublish'])->name('user.blog.posts.unpublish');

    // Categories (author's own)
    Route::get('categories', [AuthorBlogController::class, 'listCategories'])->name('user.blog.categories.index');
    Route::post('categories', [AuthorBlogController::class, 'storeCategory'])->name('user.blog.categories.store');
    Route::put('categories/{category}', [AuthorBlogController::class, 'updateCategory'])->name('user.blog.categories.update');
    Route::post('categories/{category}/request-delete', [AuthorBlogController::class, 'requestCategoryDeletion'])->name('user.blog.categories.request-delete');

    // Tags (author's own)
    Route::get('tags', [AuthorBlogController::class, 'listTags'])->name('user.blog.tags.index');
    Route::post('tags', [AuthorBlogController::class, 'storeTag'])->name('user.blog.tags.store');
    Route::put('tags/{tag}', [AuthorBlogController::class, 'updateTag'])->name('user.blog.tags.update');
    Route::post('tags/{tag}/request-delete', [AuthorBlogController::class, 'requestTagDeletion'])->name('user.blog.tags.request-delete');
});

// Blog Management - User routes (user's own posts)
Route::prefix('blog')->middleware('auth:sanctum')->group(function () {
    Route::get('my-posts', [PublicPostController::class, 'myPosts'])->name('blog.my-posts');
    Route::put('my-posts/{post}', [PublicPostController::class, 'updateUserPost'])->name('blog.my-posts.update');
    Route::delete('my-posts/{post}', [PublicPostController::class, 'destroyUserPost'])->name('blog.my-posts.destroy');
});

// Media Management - User routes
use App\Http\Controllers\Api\MediaController;

Route::prefix('media')->middleware('auth:sanctum')->group(function () {
    Route::get('', [MediaController::class, 'index'])->name('media.index');
    Route::post('', [MediaController::class, 'store'])->name('media.store');
    Route::get('{media}', [MediaController::class, 'show'])->name('media.show');
    Route::delete('{media}', [MediaController::class, 'destroy'])->name('media.destroy');
});

// Webhook routes (no auth - called by external payment gateway)
use App\Http\Controllers\Api\WebhookController;

Route::post('webhooks/pesapal', [WebhookController::class, 'pesapal'])->name('webhooks.pesapal');
Route::get('webhooks', [WebhookController::class, 'index'])->middleware('auth:sanctum');

// Phase 1: Subscription & Payment Routes (Authenticated)
use App\Http\Controllers\Api\SubscriptionCoreController;
use App\Http\Controllers\Api\StudentApprovalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RenewalController;
use App\Http\Controllers\Api\UserPaymentMethodController;

// Subscription routes
Route::prefix('subscriptions')->middleware('auth:sanctum')->group(function () {
    // Current subscription status
    Route::get('current', [SubscriptionCoreController::class, 'getCurrentSubscription'])->name('subscriptions.current');
    Route::get('status', [SubscriptionCoreController::class, 'getSubscriptionStatus'])->name('subscriptions.status');

    // Available plans
    Route::get('plans', [SubscriptionCoreController::class, 'getAvailablePlans'])->name('subscriptions.plans');

    // Renewal preferences
    Route::get('renewal-preferences', [SubscriptionCoreController::class, 'getRenewalPreferences'])->name('subscriptions.renewal-preferences');
    Route::put('renewal-preferences', [SubscriptionCoreController::class, 'updateRenewalPreferences'])->name('subscriptions.renewal-preferences.update');

    // Subscription management
    Route::post('initiate-payment', [SubscriptionCoreController::class, 'initiatePayment'])->name('subscriptions.initiate-payment');
    Route::post('cancel-payment', [SubscriptionCoreController::class, 'cancelSubscriptionPayment'])->name('subscriptions.cancel-payment');
    Route::post('process-mock-payment', [SubscriptionCoreController::class, 'processMockPayment'])->name('subscriptions.process-mock-payment');
    Route::post('cancel', [SubscriptionCoreController::class, 'cancelSubscription'])->name('subscriptions.cancel');

});
// Student approval routes
Route::prefix('student-approvals')->middleware('auth:sanctum')->group(function () {
    // User operations
    Route::post('submit', [StudentApprovalController::class, 'submitApprovalRequest'])->name('student-approvals.submit');
    Route::get('status', [StudentApprovalController::class, 'getApprovalStatus'])->name('student-approvals.status');
    Route::get('eligible', [StudentApprovalController::class, 'canRequestStudentPlan'])->name('student-approvals.eligible');

    // Admin operations
    Route::get('requests', [StudentApprovalController::class, 'listApprovalRequests'])->name('student-approvals.list');
    Route::get('requests/{request}', [StudentApprovalController::class, 'getApprovalDetails'])->name('student-approvals.show');
    Route::post('requests/{request}/approve', [StudentApprovalController::class, 'approveRequest'])->name('student-approvals.approve');
    Route::post('requests/{request}/reject', [StudentApprovalController::class, 'rejectRequest'])->name('student-approvals.reject');
});

// Payment routes
Route::prefix('payments')->group(function () {
    // Unauthenticated payment operations
    Route::get('check-status', [PaymentController::class, 'checkPaymentStatus'])->name('payments.check-status');
    Route::post('webhook', [PaymentController::class, 'handleWebhook'])->name('payments.webhook');

    // Authenticated payment operations
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('form-metadata', [PaymentController::class, 'getPaymentFormMetadata'])->name('payments.form-metadata');
        Route::post('verify', [PaymentController::class, 'verifyPayment'])->name('payments.verify');
        Route::post('process', [PaymentController::class, 'processPayment'])->name('payments.process');
        Route::get('history', [PaymentController::class, 'getPaymentHistory'])->name('payments.history');
        Route::post('refund', [PaymentController::class, 'refundPayment'])->name('payments.refund');
        Route::post('test-mock-payment', [PaymentController::class, 'createTestMockPayment'])->name('payments.test-mock-payment');
    });
});

// Admin Exchange Rate Management routes (Super Admin only)
use App\Http\Controllers\Api\Admin\BillingController;

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {  // Policy handles super_admin checks
    // Exchange rate routes already defined earlier in file
    
    // Phase 3: Reconciliation management (Finance/Admin)
    Route::prefix('reconciliation')->group(function () {
            Route::get('', [ReconciliationController::class, 'index'])->name('admin.reconciliation.index');
            Route::get('stats', [ReconciliationController::class, 'stats'])->name('admin.reconciliation.stats');
            Route::get('export', [ReconciliationController::class, 'export'])->name('admin.reconciliation.export');
            Route::post('trigger', [ReconciliationController::class, 'trigger'])->name('admin.reconciliation.trigger');
            Route::delete('{run}', [ReconciliationController::class, 'destroy'])->name('admin.reconciliation.destroy');
            Route::get('{run}', [ReconciliationController::class, 'show'])->name('admin.reconciliation.show');
    });

    // Billing and reconciliation management (Finance/Admin)
    Route::prefix('billing')->group(function () {
        Route::get('dashboard', [BillingController::class, 'getDashboardSummary'])->name('admin.billing.dashboard');

        // Reconciliation endpoints
        Route::post('reconcile/donations', [BillingController::class, 'reconcileDonations'])->name('admin.billing.reconcile-donations');
        Route::post('reconcile/orders', [BillingController::class, 'reconcileOrders'])->name('admin.billing.reconcile-orders');
        Route::get('reconcile/status', [BillingController::class, 'getReconciliationStatus'])->name('admin.billing.reconcile-status');

        // Export endpoints
        Route::get('export/donations', [BillingController::class, 'exportDonations'])->name('admin.billing.export-donations');
        Route::get('export/event-orders', [BillingController::class, 'exportEventOrders'])->name('admin.billing.export-event-orders');
        Route::get('export/donation-summary', [BillingController::class, 'exportDonationSummary'])->name('admin.billing.export-donation-summary');
        Route::get('export/event-sales-summary', [BillingController::class, 'exportEventSalesSummary'])->name('admin.billing.export-event-sales-summary');
        Route::get('export/financial-reconciliation', [BillingController::class, 'exportFinancialReconciliation'])->name('admin.billing.export-financial-reconciliation');
    });

    // System Settings
    Route::get('system-settings', [\App\Http\Controllers\Api\Admin\SystemSettingController::class, 'index'])->name('admin.system-settings.index');
    Route::put('system-settings', [\App\Http\Controllers\Api\Admin\SystemSettingController::class, 'update'])->name('admin.system-settings.update');
});

// Event Management - Public routes
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\SpeakerController;

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/{slug}', [EventController::class, 'show'])->name('events.show');
Route::get('events/{event}/tickets', [TicketController::class, 'index'])->name('events.tickets.index');
Route::get('events/{event}/speakers', [SpeakerController::class, 'index'])->name('events.speakers.index');

// Event Management - User routes (Attendees only - registration/RSVP)
Route::middleware('auth:sanctum')->group(function () {
    // Registration/RSVP
    Route::post('events/{event}/register', [RegistrationController::class, 'store'])->name('events.register');
    Route::get('registrations/my', [RegistrationController::class, 'myRegistrations'])->name('registrations.my');
    Route::delete('registrations/{registration}', [RegistrationController::class, 'destroy'])->name('registrations.destroy');
});

// Event Management - Admin routes
use App\Http\Controllers\Api\Admin\AdminEventController;

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('events/stats', [AdminEventController::class, 'stats'])->name('admin.events.stats');
    Route::get('events', [AdminEventController::class, 'index'])->name('admin.events.index');
    Route::post('events', [AdminEventController::class, 'store'])->name('admin.events.store');
    Route::get('events/{event}', [AdminEventController::class, 'show'])->name('admin.events.show');
    Route::put('events/{event}', [AdminEventController::class, 'update'])->name('admin.events.update');
    Route::post('events/{event}/publish', [AdminEventController::class, 'publish'])->name('admin.events.publish');
    Route::post('events/{event}/cancel', [AdminEventController::class, 'cancel'])->name('admin.events.cancel');
    Route::post('events/{event}/suspend', [AdminEventController::class, 'suspend'])->name('admin.events.suspend');
    Route::post('events/{event}/feature', [AdminEventController::class, 'feature'])->name('admin.events.feature');
    Route::post('events/{event}/unfeature', [AdminEventController::class, 'unfeature'])->name('admin.events.unfeature');
    Route::get('events/{event}/registrations', [AdminEventController::class, 'registrations'])->name('admin.events.registrations');
    Route::delete('events/{event}', [AdminEventController::class, 'destroy'])->name('admin.events.destroy');
    
    // Tickets management (admin only now)
    Route::post('events/{event}/tickets', [TicketController::class, 'store'])->name('admin.events.tickets.store');
    Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('admin.tickets.update');
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('admin.tickets.destroy');

    // Speakers management (admin only now)
    Route::post('events/{event}/speakers', [SpeakerController::class, 'store'])->name('admin.events.speakers.store');
    Route::put('speakers/{speaker}', [SpeakerController::class, 'update'])->name('admin.speakers.update');
    Route::delete('speakers/{speaker}', [SpeakerController::class, 'destroy'])->name('admin.speakers.destroy');

    // Attendance Tracking (Unified for RSVPs and Paid Tickets)
    Route::get('events/{event}/attendance', [\App\Http\Controllers\Api\Admin\AdminEventAttendanceController::class, 'attendees'])->name('admin.events.attendance.list');
    Route::get('events/{event}/attendance/stats', [\App\Http\Controllers\Api\Admin\AdminEventAttendanceController::class, 'stats'])->name('admin.events.attendance.stats');
    Route::post('events/{event}/attendance/scan', [\App\Http\Controllers\Api\Admin\AdminEventAttendanceController::class, 'scan'])->name('admin.events.attendance.scan');
});


// Event Categories & Tags
use App\Http\Controllers\Api\EventCategoryController;
use App\Http\Controllers\Api\EventTagController;

Route::get('event-categories', [EventCategoryController::class, 'index'])->name('event-categories.index');
Route::get('event-tags', [EventTagController::class, 'index'])->name('event-tags.index');

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('event-categories', EventCategoryController::class)->except(['index']);
    Route::apiResource('event-tags', EventTagController::class)->only(['store', 'destroy']);
});

// Groups (County-based networking hubs)
use App\Http\Controllers\Api\GroupController;

// Public group routes
Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
Route::get('groups/{slug}', [GroupController::class, 'show'])->name('groups.show');
Route::get('groups/{slug}/members', [GroupController::class, 'members'])->name('groups.members');

// Authenticated group actions
Route::middleware('auth:sanctum')->group(function () {
    Route::post('groups/{slug}/join', [GroupController::class, 'join'])->name('groups.join');
    Route::post('groups/{slug}/leave', [GroupController::class, 'leave'])->name('groups.leave');
});

// Forum API Routes
use App\Http\Controllers\Api\ForumCategoryController;
use App\Http\Controllers\Api\ForumThreadController;
use App\Http\Controllers\Api\ForumPostController;
use App\Http\Controllers\Api\ForumTagController;

Route::prefix('forum')->group(function () {
    // Categories
    Route::get('categories', [ForumCategoryController::class, 'index'])->name('forum.categories.index');
    Route::get('categories/{category}', [ForumCategoryController::class, 'show'])->name('forum.categories.show');
    
    // Threads - list all or filter by category
    Route::get('threads', [ForumThreadController::class, 'index'])->name('forum.threads.index');
    Route::get('categories/{category}/threads', [ForumThreadController::class, 'index'])->name('forum.categories.threads');
    Route::get('threads/{thread}', [ForumThreadController::class, 'show'])->name('forum.threads.show');
    
    // Posts for a thread
    Route::get('threads/{thread}/posts', [ForumPostController::class, 'index'])->name('forum.posts.index');
    
    // Authenticated forum actions
    Route::middleware('auth:sanctum')->group(function () {
        // Thread CRUD
        Route::post('categories/{category}/threads', [ForumThreadController::class, 'store'])->name('forum.threads.store');
        Route::put('threads/{thread}', [ForumThreadController::class, 'update'])->name('forum.threads.update');
        Route::delete('threads/{thread}', [ForumThreadController::class, 'destroy'])->name('forum.threads.destroy');
        
        // Thread moderation (admin/moderator)
        Route::post('threads/{thread}/pin', [ForumThreadController::class, 'pin'])->name('forum.threads.pin');
        Route::post('threads/{thread}/unpin', [ForumThreadController::class, 'unpin'])->name('forum.threads.unpin');
        Route::post('threads/{thread}/lock', [ForumThreadController::class, 'lock'])->name('forum.threads.lock');
        Route::post('threads/{thread}/unlock', [ForumThreadController::class, 'unlock'])->name('forum.threads.unlock');
        
        // Post CRUD
        Route::post('threads/{thread}/posts', [ForumPostController::class, 'store'])->name('forum.posts.store');
        Route::put('posts/{post}', [ForumPostController::class, 'update'])->name('forum.posts.update');
        Route::delete('posts/{post}', [ForumPostController::class, 'destroy'])->name('forum.posts.destroy');
    });
    
    // Admin-only category management
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('categories', [ForumCategoryController::class, 'store'])->name('forum.categories.store');
        Route::put('categories/{category}', [ForumCategoryController::class, 'update'])->name('forum.categories.update');
        Route::delete('categories/{category}', [ForumCategoryController::class, 'destroy'])->name('forum.categories.destroy');
    });

    // Tags - public read, admin CUD
    Route::get('tags', [ForumTagController::class, 'index'])->name('forum.tags.index');
    Route::get('tags/{tag}', [ForumTagController::class, 'show'])->name('forum.tags.show');
    
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('tags', [ForumTagController::class, 'store'])->name('forum.tags.store');
        Route::put('tags/{tag}', [ForumTagController::class, 'update'])->name('forum.tags.update');
        Route::delete('tags/{tag}', [ForumTagController::class, 'destroy'])->name('forum.tags.destroy');
    });

    // Forum Users - member directory
    Route::get('users', [\App\Http\Controllers\Api\ForumUserController::class, 'index'])->name('forum.users.index');
});

// Support Ticket Routes
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\Admin\AdminSupportTicketController;

Route::prefix('support')->middleware('auth:sanctum')->group(function () {
    Route::get('tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
    Route::post('tickets', [SupportTicketController::class, 'store'])->name('support.tickets.store');
    Route::get('tickets/{id}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
    Route::post('tickets/{id}/responses', [SupportTicketController::class, 'addResponse'])->name('support.tickets.responses.store');
});

Route::prefix('admin/support')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('tickets', [AdminSupportTicketController::class, 'index'])->name('admin.support.tickets.index');
    Route::get('tickets/{id}', [AdminSupportTicketController::class, 'show'])->name('admin.support.tickets.show');
    Route::post('tickets/{id}/responses', [AdminSupportTicketController::class, 'addResponse'])->name('admin.support.tickets.responses.store');
    Route::post('tickets/{id}/assign', [AdminSupportTicketController::class, 'assign'])->name('admin.support.tickets.assign');
    Route::patch('tickets/{id}/status', [AdminSupportTicketController::class, 'updateStatus'])->name('admin.support.tickets.status');
});

// Public Profile Routes
use App\Http\Controllers\Api\PublicProfileController;

// Public: View user profile
Route::get('users/{username}/public', [PublicProfileController::class, 'show'])->name('profile.public.show');

// Authenticated: Privacy settings
Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile/privacy-settings', [PublicProfileController::class, 'getPrivacySettings'])->name('profile.privacy.get');
    Route::put('profile/privacy-settings', [PublicProfileController::class, 'updatePrivacySettings'])->name('profile.privacy.update');
    Route::get('profile/preview', [PublicProfileController::class, 'preview'])->name('profile.preview');
});

// Lab Space Booking Routes
use App\Http\Controllers\Api\LabSpaceController;
use App\Http\Controllers\Api\LabBookingController;
use App\Http\Controllers\Api\Admin\AdminLabSpaceController;
use App\Http\Controllers\Api\Admin\AdminLabBookingController;

// Public lab space routes (anyone can browse)
Route::prefix('spaces')->group(function () {
    Route::get('/', [LabSpaceController::class, 'index'])->name('lab-spaces.index');
    Route::get('/{slug}', [LabSpaceController::class, 'show'])->name('lab-spaces.show');
    Route::get('/{slug}/availability', [LabSpaceController::class, 'availability'])->name('lab-spaces.availability');
});

// Authenticated lab booking routes
Route::prefix('bookings')->middleware('auth:sanctum')->group(function () {
    Route::get('/quota', [LabBookingController::class, 'quotaStatus'])->name('lab-bookings.quota');
    Route::get('/', [LabBookingController::class, 'index'])->name('lab-bookings.index');
    Route::post('/', [LabBookingController::class, 'store'])->name('lab-bookings.store');
    Route::get('/{id}', [LabBookingController::class, 'show'])->name('lab-bookings.show');
    Route::delete('/{id}', [LabBookingController::class, 'destroy'])->name('lab-bookings.destroy');
});

// Admin lab management routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // Lab Spaces CRUD
    Route::get('spaces', [AdminLabSpaceController::class, 'index'])->name('admin.lab-spaces.index');
    Route::post('spaces', [AdminLabSpaceController::class, 'store'])->name('admin.lab-spaces.store');
    Route::get('spaces/{id}', [AdminLabSpaceController::class, 'show'])->name('admin.lab-spaces.show');
    Route::put('spaces/{id}', [AdminLabSpaceController::class, 'update'])->name('admin.lab-spaces.update');
    Route::delete('spaces/{id}', [AdminLabSpaceController::class, 'destroy'])->name('admin.lab-spaces.destroy');

    // Lab Bookings Management
    Route::get('lab-bookings', [AdminLabBookingController::class, 'index'])->name('admin.lab-bookings.index');
    Route::get('lab-bookings/{id}', [AdminLabBookingController::class, 'show'])->name('admin.lab-bookings.show');
    Route::put('lab-bookings/{id}/approve', [AdminLabBookingController::class, 'approve'])->name('admin.lab-bookings.approve');
    Route::put('lab-bookings/{id}/reject', [AdminLabBookingController::class, 'reject'])->name('admin.lab-bookings.reject');
    Route::put('lab-bookings/{id}/check-in', [AdminLabBookingController::class, 'checkIn'])->name('admin.lab-bookings.check-in');
    Route::put('lab-bookings/{id}/check-out', [AdminLabBookingController::class, 'checkOut'])->name('admin.lab-bookings.check-out');
    Route::put('lab-bookings/{id}/no-show', [AdminLabBookingController::class, 'markNoShow'])->name('admin.lab-bookings.no-show');
    Route::get('lab-bookings/stats', [AdminLabBookingController::class, 'stats'])->name('admin.lab-bookings.stats');

    // Lab Maintenance Blocks
    Route::get('lab-maintenance', [\App\Http\Controllers\Api\Admin\AdminLabMaintenanceController::class, 'index'])->name('admin.lab-maintenance.index');
    Route::post('lab-maintenance', [\App\Http\Controllers\Api\Admin\AdminLabMaintenanceController::class, 'store'])->name('admin.lab-maintenance.store');
    Route::get('lab-maintenance/{id}', [\App\Http\Controllers\Api\Admin\AdminLabMaintenanceController::class, 'show'])->name('admin.lab-maintenance.show');
    Route::put('lab-maintenance/{id}', [\App\Http\Controllers\Api\Admin\AdminLabMaintenanceController::class, 'update'])->name('admin.lab-maintenance.update');
    Route::delete('lab-maintenance/{id}', [\App\Http\Controllers\Api\Admin\AdminLabMaintenanceController::class, 'destroy'])->name('admin.lab-maintenance.destroy');

    // Forum Stats
    Route::get('forum/stats', [\App\Http\Controllers\Api\Admin\ForumStatsController::class, 'index'])->name('admin.forum.stats');

    // Forum Groups Management
    Route::get('groups', [\App\Http\Controllers\Api\Admin\AdminGroupController::class, 'index'])->name('admin.groups.index');
    Route::post('groups', [\App\Http\Controllers\Api\Admin\AdminGroupController::class, 'store'])->name('admin.groups.store');
    Route::put('groups/{group}', [\App\Http\Controllers\Api\Admin\AdminGroupController::class, 'update'])->name('admin.groups.update');
    Route::delete('groups/{group}', [\App\Http\Controllers\Api\Admin\AdminGroupController::class, 'destroy'])->name('admin.groups.destroy');
    Route::get('groups/{group}/members', [\App\Http\Controllers\Api\Admin\AdminGroupController::class, 'members'])->name('admin.groups.members');
    Route::delete('groups/{group}/members/{id}', [\App\Http\Controllers\Api\Admin\AdminGroupController::class, 'removeMember'])->name('admin.groups.members.remove');

    // System Features Management
    Route::get('system-features', [\App\Http\Controllers\Api\Admin\SystemFeatureController::class, 'index'])->name('admin.system-features.index');
    Route::get('system-features/{feature}', [\App\Http\Controllers\Api\Admin\SystemFeatureController::class, 'show'])->name('admin.system-features.show');
    Route::put('system-features/{feature}', [\App\Http\Controllers\Api\Admin\SystemFeatureController::class, 'update'])->name('admin.system-features.update');
    Route::post('system-features/{feature}/toggle', [\App\Http\Controllers\Api\Admin\SystemFeatureController::class, 'toggle'])->name('admin.system-features.toggle');

    // Refund Management
    Route::prefix('refunds')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\RefundController::class, 'index'])->name('admin.refunds.index');
        Route::get('/stats', [\App\Http\Controllers\Api\Admin\RefundController::class, 'stats'])->name('admin.refunds.stats');
        Route::get('/{refund}', [\App\Http\Controllers\Api\Admin\RefundController::class, 'show'])->name('admin.refunds.show');
        Route::post('/{refund}/approve', [\App\Http\Controllers\Api\Admin\RefundController::class, 'approve'])->name('admin.refunds.approve');
        Route::post('/{refund}/reject', [\App\Http\Controllers\Api\Admin\RefundController::class, 'reject'])->name('admin.refunds.reject');
        Route::post('/{refund}/process', [\App\Http\Controllers\Api\Admin\RefundController::class, 'process'])->name('admin.refunds.process');
    });
});

// Event Ticket Orders
use App\Http\Controllers\Api\EventOrderController;

// Public ticket routes (no auth required for guest checkout)
Route::post('events/{event}/purchase', [EventOrderController::class, 'purchase'])->name('event-orders.purchase');
Route::get('event-orders/status/{reference}', [EventOrderController::class, 'checkPaymentStatus'])->name('event-orders.status');

// Authenticated ticket routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('my-tickets', [EventOrderController::class, 'myTickets'])->name('event-orders.my-tickets');
    Route::get('event-orders/{order}', [EventOrderController::class, 'show'])->name('event-orders.show');
    
    // Organizer check-in (requires event update permission)
    Route::post('events/{event}/scan-ticket', [EventOrderController::class, 'scanTicket'])->name('event-orders.scan');
});

Route::prefix('admin/audit-logs')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');
    Route::get('/model-history', [AuditLogController::class, 'getModelAuditLog'])->name('admin.audit-logs.model-history');
    Route::get('/user-actions', [AuditLogController::class, 'getUserAuditLog'])->name('admin.audit-logs.user-actions');
});

// ============================================================================
// BLOG ROUTES
// ============================================================================

// Public Blog (unauthenticated)
Route::prefix('blog')->group(function () {
    Route::get('posts', [\App\Http\Controllers\Api\PublicPostController::class, 'index'])->name('blog.posts.index');
    Route::get('posts/{identifier}', [\App\Http\Controllers\Api\PublicPostController::class, 'show'])->name('blog.posts.show');
    Route::get('categories', [\App\Http\Controllers\Api\PublicPostController::class, 'categories'])->name('blog.categories.index');
    Route::get('tags', [\App\Http\Controllers\Api\PublicPostController::class, 'tags'])->name('blog.tags.index');
});

// Author/Subscriber Blog Routes (authenticated)
Route::prefix('author')->middleware('auth:sanctum')->group(function () {
    Route::get('posts', [\App\Http\Controllers\Api\AuthorPostController::class, 'index'])->name('author.posts.index');
    Route::get('posts/create', [\App\Http\Controllers\Api\AuthorPostController::class, 'create'])->name('author.posts.create');
    Route::post('posts', [\App\Http\Controllers\Api\AuthorPostController::class, 'store'])->name('author.posts.store');
    Route::get('posts/{slug}', [\App\Http\Controllers\Api\AuthorPostController::class, 'show'])->name('author.posts.show');
    Route::get('posts/{slug}/edit', [\App\Http\Controllers\Api\AuthorPostController::class, 'edit'])->name('author.posts.edit');
    Route::put('posts/{slug}', [\App\Http\Controllers\Api\AuthorPostController::class, 'update'])->name('author.posts.update');
    Route::delete('posts/{slug}', [\App\Http\Controllers\Api\AuthorPostController::class, 'destroy'])->name('author.posts.destroy');
    Route::post('posts/{slug}/restore', [\App\Http\Controllers\Api\AuthorPostController::class, 'restore'])->name('author.posts.restore');
    Route::post('posts/{slug}/publish', [\App\Http\Controllers\Api\AuthorPostController::class, 'publish'])->name('author.posts.publish');
    Route::post('posts/{slug}/unpublish', [\App\Http\Controllers\Api\AuthorPostController::class, 'unpublish'])->name('author.posts.unpublish');
});

// Subscriber Tag/Category Routes (authenticated, can create/update own)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('tags', [\App\Http\Controllers\Api\TagAdminController::class, 'store'])->name('tags.store');
    Route::put('tags/{tag}', [\App\Http\Controllers\Api\TagAdminController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [\App\Http\Controllers\Api\TagAdminController::class, 'destroy'])->name('tags.destroy');

    Route::post('categories', [\App\Http\Controllers\Api\CategoryAdminController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [\App\Http\Controllers\Api\CategoryAdminController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [\App\Http\Controllers\Api\CategoryAdminController::class, 'destroy'])->name('categories.destroy');
});

// Admin Blog Routes (staff only)
Route::prefix('admin/blog')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('posts', [\App\Http\Controllers\Api\PostAdminController::class, 'index'])->name('admin.posts.index');
    Route::get('posts/{id}', [\App\Http\Controllers\Api\PostAdminController::class, 'show'])->name('admin.posts.show');
    Route::put('posts/{id}', [\App\Http\Controllers\Api\PostAdminController::class, 'update'])->name('admin.posts.update');
    Route::delete('posts/{id}', [\App\Http\Controllers\Api\PostAdminController::class, 'destroy'])->name('admin.posts.destroy');
    Route::post('posts/{id}/publish', [\App\Http\Controllers\Api\PostAdminController::class, 'publish'])->name('admin.posts.publish');
    Route::post('posts/{id}/unpublish', [\App\Http\Controllers\Api\PostAdminController::class, 'unpublish'])->name('admin.posts.unpublish');
    Route::post('posts/{id}/restore', [\App\Http\Controllers\Api\PostAdminController::class, 'restore'])->name('admin.posts.restore');

    // Tag/Category Admin with affected posts check (Note: these might need to be in admin/blog too)
    Route::get('tags/{tag}/affected-posts', [\App\Http\Controllers\Api\TagAdminController::class, 'affectedPosts'])->name('admin.tags.affected-posts');
    Route::get('categories/{category}/affected-posts', [\App\Http\Controllers\Api\CategoryAdminController::class, 'affectedPosts'])->name('admin.categories.affected-posts');
});

// Forum API Routes
Route::prefix('forum')->group(function () {
    // Categories
    Route::get('categories', [\App\Http\Controllers\Api\ForumCategoryController::class, 'index'])->name('forum.categories.index');
    Route::get('categories/{category}', [\App\Http\Controllers\Api\ForumCategoryController::class, 'show'])->name('forum.categories.show');
    
    // Threads - list all or filter by category
    Route::get('threads', [\App\Http\Controllers\Api\ForumThreadController::class, 'index'])->name('forum.threads.index');
    Route::get('categories/{category}/threads', [\App\Http\Controllers\Api\ForumThreadController::class, 'index'])->name('forum.categories.threads');
    Route::get('threads/{thread}', [\App\Http\Controllers\Api\ForumThreadController::class, 'show'])->name('forum.threads.show');
    
    // Posts for a thread
    Route::get('threads/{thread}/posts', [\App\Http\Controllers\Api\ForumPostController::class, 'index'])->name('forum.posts.index');
    
    // Authenticated forum actions (subscribers only)
    Route::middleware('auth:sanctum')->group(function () {
        // Thread CRUD
        Route::post('categories/{category}/threads', [\App\Http\Controllers\Api\ForumThreadController::class, 'store'])->name('forum.threads.store');
        Route::put('threads/{thread}', [\App\Http\Controllers\Api\ForumThreadController::class, 'update'])->name('forum.threads.update');
        Route::delete('threads/{thread}', [\App\Http\Controllers\Api\ForumThreadController::class, 'destroy'])->name('forum.threads.destroy');
        
        // Thread moderation (staff only)
        Route::post('threads/{thread}/pin', [\App\Http\Controllers\Api\ForumThreadController::class, 'pin'])->name('forum.threads.pin');
        Route::post('threads/{thread}/unpin', [\App\Http\Controllers\Api\ForumThreadController::class, 'unpin'])->name('forum.threads.unpin');
        Route::post('threads/{thread}/lock', [\App\Http\Controllers\Api\ForumThreadController::class, 'lock'])->name('forum.threads.lock');
        Route::post('threads/{thread}/unlock', [\App\Http\Controllers\Api\ForumThreadController::class, 'unlock'])->name('forum.threads.unlock');
        
        // Post CRUD
        Route::post('threads/{thread}/posts', [\App\Http\Controllers\Api\ForumPostController::class, 'store'])->name('forum.posts.store');
        Route::put('posts/{post}', [\App\Http\Controllers\Api\ForumPostController::class, 'update'])->name('forum.posts.update');
        Route::delete('posts/{post}', [\App\Http\Controllers\Api\ForumPostController::class, 'destroy'])->name('forum.posts.destroy');
    });
    
    // Category management - auth for create/update, admin for delete (matches blog pattern)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('categories', [\App\Http\Controllers\Api\ForumCategoryController::class, 'store'])->name('forum.categories.store');
        Route::put('categories/{category}', [\App\Http\Controllers\Api\ForumCategoryController::class, 'update'])->name('forum.categories.update');
    });
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::delete('categories/{category}', [\App\Http\Controllers\Api\ForumCategoryController::class, 'destroy'])->name('forum.categories.destroy');
    });

    // Tags - public read, auth create/update, admin delete (matches blog pattern)
    Route::get('tags', [\App\Http\Controllers\Api\ForumTagController::class, 'index'])->name('forum.tags.index');
    Route::get('tags/{tag}', [\App\Http\Controllers\Api\ForumTagController::class, 'show'])->name('forum.tags.show');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('tags', [\App\Http\Controllers\Api\ForumTagController::class, 'store'])->name('forum.tags.store');
        Route::put('tags/{tag}', [\App\Http\Controllers\Api\ForumTagController::class, 'update'])->name('forum.tags.update');
    });
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::delete('tags/{tag}', [\App\Http\Controllers\Api\ForumTagController::class, 'destroy'])->name('forum.tags.destroy');
    });

    // Groups - county-based community groups
    Route::get('groups', [\App\Http\Controllers\Api\GroupController::class, 'index'])->name('forum.groups.index');
    Route::get('groups/{slug}', [\App\Http\Controllers\Api\GroupController::class, 'show'])->name('forum.groups.show');
    Route::get('groups/{slug}/members', [\App\Http\Controllers\Api\GroupController::class, 'members'])->name('forum.groups.members');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('groups/{slug}/join', [\App\Http\Controllers\Api\GroupController::class, 'join'])->name('forum.groups.join');
        Route::post('groups/{slug}/leave', [\App\Http\Controllers\Api\GroupController::class, 'leave'])->name('forum.groups.leave');
    });

    // Forum Users - member directory
    Route::get('users', [\App\Http\Controllers\Api\ForumUserController::class, 'index'])->name('forum.users.index');
});