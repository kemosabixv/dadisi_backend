<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Observers\PlanSubscriptionObserver;
use App\Models\PlanSubscription;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Service Contracts for Dependency Injection
        // =====================================================
        
        // Currency/Exchange Rate Service
        $this->app->bind(
            \App\Services\Contracts\ExchangeRateServiceContract::class,
            \App\Services\ExchangeRateService::class
        );


        // Subscription Core Service
        $this->app->bind(
            \App\Services\Contracts\SubscriptionCoreServiceContract::class,
            \App\Services\SubscriptionCoreService::class
        );

        // Lab Booking Service
        $this->app->bind(
            \App\Services\Contracts\LabBookingServiceContract::class,
            \App\Services\LabBookingService::class
        );

        // =====================================================
        // Phase 3 Service Layer - Domain Services
        // =====================================================

        // User Service
        $this->app->bind(
            \App\Services\Contracts\UserServiceContract::class,
            \App\Services\Users\UserService::class
        );

        // County Service
        $this->app->bind(
            \App\Services\Contracts\CountyServiceContract::class,
            \App\Services\CountyService::class
        );

        // User Role Service
        $this->app->bind(
            \App\Services\Contracts\UserRoleServiceContract::class,
            \App\Services\Users\UserRoleService::class
        );

        // User Bulk Operation Service
        $this->app->bind(
            \App\Services\Contracts\UserBulkOperationServiceContract::class,
            \App\Services\Users\UserBulkOperationService::class
        );

        // Event Registration Service
        $this->app->bind(
            \App\Services\Contracts\EventRegistrationServiceContract::class,
            \App\Services\Events\EventRegistrationService::class
        );

        // Event Taxonomy Service
        $this->app->bind(
            \App\Services\Contracts\EventTaxonomyServiceContract::class,
            \App\Services\Events\EventTaxonomyService::class
        );

        // User Invitation Service
        $this->app->bind(
            \App\Services\Contracts\UserInvitationServiceContract::class,
            \App\Services\Users\UserInvitationService::class
        );

        // Plan Service
        $this->app->bind(
            \App\Services\Contracts\PlanServiceContract::class,
            \App\Services\PlanService::class
        );

        // Donation Service (Phase 3)
        $this->app->bind(
            \App\Services\Contracts\DonationServiceContract::class,
            \App\Services\Donations\DonationService::class
        );

        // Donation Campaign Service
        $this->app->bind(
            \App\Services\Contracts\DonationCampaignServiceContract::class,
            \App\Services\Donations\DonationCampaignService::class
        );

        // Event Service (Phase 3)
        $this->app->bind(
            \App\Services\Contracts\EventServiceContract::class,
            \App\Services\Events\EventService::class
        );

        // Event Ticket Service
        $this->app->bind(
            \App\Services\Contracts\EventTicketServiceContract::class,
            \App\Services\Events\EventTicketService::class
        );

        // Support Ticket Service
        $this->app->bind(
            \App\Services\Contracts\SupportTicketServiceContract::class,
            \App\Services\Support\SupportTicketService::class
        );

        // Post/Blog Service
        $this->app->bind(
            \App\Services\Contracts\PostServiceContract::class,
            \App\Services\Blog\PostService::class
        );

        // Blog Taxonomy Service
        $this->app->bind(
            \App\Services\Contracts\BlogTaxonomyServiceContract::class,
            \App\Services\Blog\BlogTaxonomyService::class
        );

        // Payment Service (Phase 3)
        $this->app->bind(
            \App\Services\Contracts\PaymentServiceContract::class,
            \App\Services\Payments\PaymentService::class
        );

        // Forum Service
        $this->app->bind(
            \App\Services\Contracts\ForumServiceContract::class,
            \App\Services\Forums\ThreadService::class
        );

        // Forum Post Service
        $this->app->bind(
            \App\Services\Contracts\ForumPostServiceContract::class,
            \App\Services\Forums\ForumPostService::class
        );

        // Forum Taxonomy Service
        $this->app->bind(
            \App\Services\Contracts\ForumTaxonomyServiceContract::class,
            \App\Services\Forums\ForumTaxonomyService::class
        );

        // Forum User Service
        $this->app->bind(
            \App\Services\Contracts\ForumUserServiceContract::class,
            \App\Services\Forums\ForumUserService::class
        );

        // Lab Management Service
        $this->app->bind(
            \App\Services\Contracts\LabManagementServiceContract::class,
            \App\Services\LabManagement\LabService::class
        );

        // Promo Code Service
        $this->app->bind(
            \App\Services\Contracts\PromoCodeServiceContract::class,
            \App\Services\PromoCodes\PromoCodeService::class
        );


        // Permission Service
        $this->app->bind(
            \App\Services\Contracts\PermissionServiceContract::class,
            \App\Services\Permissions\PermissionService::class
        );

        // User Data Retention Service
        $this->app->bind(
            \App\Services\Contracts\UserDataRetentionServiceContract::class,
            \App\Services\UserDataRetention\DataRetentionService::class
        );

        // Reconciliation Service
        $this->app->bind(
            \App\Services\Contracts\ReconciliationServiceContract::class,
            \App\Services\Reconciliation\FinancialReconciliationService::class
        );

        // Media Service
        $this->app->bind(
            \App\Services\Contracts\MediaServiceContract::class,
            \App\Services\Media\MediaService::class
        );

        // Public Profile Service
        $this->app->bind(
            \App\Services\Contracts\PublicProfileServiceContract::class,
            \App\Services\Profile\PublicProfileService::class
        );

        // Group Service
        $this->app->bind(
            \App\Services\Contracts\GroupServiceContract::class,
            \App\Services\GroupService::class
        );

        // Refund Service
        $this->app->bind(
            \App\Services\Contracts\RefundServiceContract::class,
            \App\Services\RefundService::class
        );

        // System Setting Service
        $this->app->bind(
            \App\Services\Contracts\SystemSettingServiceContract::class,
            \App\Services\SystemSettingService::class
        );

        // Event Attendance Service
        $this->app->bind(
            \App\Services\Contracts\EventAttendanceServiceContract::class,
            \App\Services\EventAttendanceService::class
        );

        // Billing Service
        $this->app->bind(
            \App\Services\Contracts\BillingServiceContract::class,
            \App\Services\BillingService::class
        );

        // Billing Export Service
        $this->app->bind(
            \App\Services\Contracts\BillingExportServiceContract::class,
            \App\Services\BillingExportService::class
        );

        // System Feature Service
        $this->app->bind(
            \App\Services\Contracts\SystemFeatureServiceContract::class,
            \App\Services\SystemFeatureService::class
        );


        // Menu Service
        $this->app->bind(
            \App\Services\Contracts\MenuServiceContract::class,
            \App\Services\MenuService::class
        );

        // Notification Service
        $this->app->bind(
            \App\Services\Contracts\NotificationServiceContract::class,
            \App\Services\NotificationService::class
        );

        // Role Service
        $this->app->bind(
            \App\Services\Contracts\RoleServiceContract::class,
            \App\Services\RoleService::class
        );

        // Webhook Service
        $this->app->bind(
            \App\Services\Contracts\WebhookServiceContract::class,
            \App\Services\WebhookService::class
        );

        // Message Service
        $this->app->bind(
            \App\Services\Contracts\MessageServiceContract::class,
            \App\Services\MessageService::class
        );

        // Speaker Service
        $this->app->bind(
            \App\Services\Contracts\SpeakerServiceContract::class,
            \App\Services\SpeakerService::class
        );

        // Student Approval Service
        $this->app->bind(
            \App\Services\Contracts\StudentApprovalServiceContract::class,
            \App\Services\StudentApprovalService::class
        );

        // UI Permission Service
        $this->app->bind(
            \App\Services\Contracts\UIPermissionServiceContract::class,
            \App\Services\UIPermissionService::class
        );

        // User Payment Method Service
        $this->app->bind(
            \App\Services\Contracts\UserPaymentMethodServiceContract::class,
            \App\Services\UserPaymentMethodService::class
        );

        // Renewal Reminder Service
        $this->app->bind(
            \App\Services\Contracts\RenewalReminderServiceContract::class,
            \App\Services\RenewalReminderService::class
        );

        // Event Order Service
        $this->app->bind(
            \App\Services\Contracts\EventOrderServiceContract::class,
            \App\Services\EventOrderService::class
        );

        // Key Management Service
        $this->app->bind(
            \App\Services\Contracts\KeyManagementServiceContract::class,
            \App\Services\KeyManagementService::class
        );

        // Register Payment Gateway Manager for DI
        $this->app->singleton(\App\Services\PaymentGateway\GatewayManager::class, function ($app) {
            return new \App\Services\PaymentGateway\GatewayManager(
                $app->make(\App\Services\Contracts\SystemSettingServiceContract::class)
            );
        });

        // Register QR Code Facade Alias
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            \Illuminate\Foundation\AliasLoader::getInstance()->alias(
                'QrCode', 
                \SimpleSoftwareIO\QrCode\Facades\QrCode::class
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Bind permission and role route parameters to lookup by id (not name)
        Route::bind('permission', function ($value) {
            return Permission::findOrFail($value);
        });

        Route::bind('role', function ($value) {
            return Role::findOrFail($value);
        });

        // Register model observers for data integrity
        PlanSubscription::observe(PlanSubscriptionObserver::class);
    }
}
