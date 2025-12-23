<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\County;
use App\Models\ForumTag;
use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\Plan;
use App\Models\ReconciliationRun;
use App\Models\DonationCampaign;
use App\Policies\CountyPolicy;
use App\Policies\DonationCampaignPolicy;
use App\Policies\ForumTagPolicy;
use App\Policies\LabBookingPolicy;
use App\Policies\LabSpacePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ReconciliationRunPolicy;
use App\Policies\RolePolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        County::class => CountyPolicy::class,
        DonationCampaign::class => DonationCampaignPolicy::class,
        ForumTag::class => ForumTagPolicy::class,
        LabBooking::class => LabBookingPolicy::class,
        LabSpace::class => LabSpacePolicy::class,
        Permission::class => PermissionPolicy::class,
        Plan::class => PlanPolicy::class,
        ReconciliationRun::class => ReconciliationRunPolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
