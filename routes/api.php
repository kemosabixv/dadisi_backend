<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Admin\ReconciliationController;

Route::prefix('auth')->group(function () {
	Route::post('signup', [AuthController::class, 'signup'])->name('auth.signup');
	Route::post('login', [AuthController::class, 'login'])->name('auth.login');
	Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
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

    // Update retention days for specific data types
    Route::post('retention-settings/update-days', [UserDataRetentionController::class, 'updateRetentionDays']);

    // Scheduler management
    Route::get('schedulers', [UserDataRetentionController::class, 'getSchedulers']);
    Route::post('schedulers/update', [UserDataRetentionController::class, 'updateScheduler']);
});

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\AdminMenuController;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// Route model bindings: allow lookup by name for permissions and roles used in tests
Route::bind('permission', function ($value) {
    return Permission::where('name', $value)->firstOrFail();
});

Route::bind('role', function ($value) {
    return Role::where('name', $value)->firstOrFail();
});

// Admin Menu routes (returns only authorized menu items for current user)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('admin/menu', [AdminMenuController::class, 'index'])->name('admin.menu');
});

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

// Plan Management routes (Super Admin only)
use App\Http\Controllers\Api\PlanController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::get('plans/{plan}', [PlanController::class, 'show'])->name('plans.show');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
});

// Blog Management - Public routes (read-only)
use App\Http\Controllers\Api\PublicPostController;

Route::prefix('blog')->group(function () {
    Route::get('posts', [PublicPostController::class, 'index'])->name('blog.posts.index');
    Route::get('posts/{post}', [PublicPostController::class, 'show'])->name('blog.posts.show');
});

// Blog Management - Admin routes (CRUD)
use App\Http\Controllers\Api\PostAdminController;
use App\Http\Controllers\Api\CategoryAdminController;
use App\Http\Controllers\Api\TagAdminController;

Route::prefix('admin/blog')->middleware('auth:sanctum')->group(function () {
    // Posts management - explicit routes for full control
    Route::get('posts', [PostAdminController::class, 'index'])->name('admin.blog.posts.index');
    Route::get('posts/create', [PostAdminController::class, 'create'])->name('admin.blog.posts.create');
    Route::post('posts', [PostAdminController::class, 'store'])->name('admin.blog.posts.store');
    Route::get('posts/{post}', [PostAdminController::class, 'show'])->name('admin.blog.posts.show');
    Route::get('posts/{post}/edit', [PostAdminController::class, 'edit'])->name('admin.blog.posts.edit');
    Route::put('posts/{post}', [PostAdminController::class, 'update'])->name('admin.blog.posts.update');
    Route::delete('posts/{post}', [PostAdminController::class, 'destroy'])->name('admin.blog.posts.destroy');
    Route::post('posts/{post}/restore', [PostAdminController::class, 'restore'])->name('admin.blog.posts.restore');
    Route::delete('posts/{post}/force', [PostAdminController::class, 'forceDelete'])->name('admin.blog.posts.forceDelete');

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
use App\Http\Controllers\Api\Admin\AutoRenewalJobController;

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
    Route::post('process-mock-payment', [SubscriptionCoreController::class, 'processMockPayment'])->name('subscriptions.process-mock-payment');
    Route::post('cancel', [SubscriptionCoreController::class, 'cancelSubscription'])->name('subscriptions.cancel');

    // Manual renewal endpoints (Phase 2)
    Route::post('{id}/renew/manual', [RenewalController::class, 'requestManualRenewal'])->name('subscriptions.renew.manual');
    Route::get('{id}/renewal-options', [RenewalController::class, 'getManualRenewalOptions'])->name('subscriptions.renew.options');
    Route::post('{id}/confirm-renewal', [RenewalController::class, 'confirmManualRenewal'])->name('subscriptions.renew.confirm');
    Route::get('reminders', [RenewalController::class, 'getPendingReminders'])->name('subscriptions.reminders');
    Route::post('{id}/extend-grace-period', [RenewalController::class, 'extendGracePeriod'])->name('subscriptions.extend-grace');
    // User stored payment methods (Phase 2.C)
    Route::get('payment-methods', [UserPaymentMethodController::class, 'index'])->name('payments.methods.index');
    Route::post('payment-methods', [UserPaymentMethodController::class, 'store'])->name('payments.methods.store');
    Route::put('payment-methods/{id}', [UserPaymentMethodController::class, 'update'])->name('payments.methods.update');
    Route::delete('payment-methods/{id}', [UserPaymentMethodController::class, 'destroy'])->name('payments.methods.destroy');
    Route::post('payment-methods/{id}/primary', [UserPaymentMethodController::class, 'setPrimary'])->name('payments.methods.setPrimary');
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
    });
});

// Admin Exchange Rate Management routes (Super Admin only)
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Admin\BillingController;

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {  // Policy handles super_admin checks
    Route::get('exchange-rates', [AdminController::class, 'getExchangeRate']);
    Route::get('exchange-rates/info', [AdminController::class, 'getExchangeRateInfo']);
    Route::post('exchange-rates/refresh', [AdminController::class, 'refreshExchangeRate']);
    Route::put('exchange-rates/settings', [AdminController::class, 'updateCacheSettings']);
    Route::put('exchange-rates/rate', [AdminController::class, 'updateManualRate']);

    // Auto-renewal job management (admin)
    Route::get('auto-renewal-jobs', [AutoRenewalJobController::class, 'index'])->name('admin.auto_renewal_jobs.index');
    Route::get('auto-renewal-jobs/{id}', [AutoRenewalJobController::class, 'show'])->name('admin.auto_renewal_jobs.show');
    Route::post('auto-renewal-jobs/{id}/retry', [AutoRenewalJobController::class, 'retry'])->name('admin.auto_renewal_jobs.retry');
    Route::post('auto-renewal-jobs/{id}/cancel', [AutoRenewalJobController::class, 'cancel'])->name('admin.auto_renewal_jobs.cancel');

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
});

// Additional API routes can be added here
