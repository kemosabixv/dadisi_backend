<?php

namespace Database\Seeders;

use Database\Seeders\CountiesTableSeeder;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\ExchangeRateSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedMandatory();

        // Only seed demo content in non-production environments
        if (!app()->isProduction() && !app()->environment('production')) {
            $this->command->info('Non-production environment detected. Seeding demo content...');
            $this->seedDemoContent();
        }
    }

    /**
     * Seed mandatory system data required for operation.
     */
    protected function seedMandatory(): void
    {
        $this->command->info('Seeding mandatory system data...');

        // 1. Core RBAC
        $this->call([
            RolesPermissionsSeeder::class,
        ]);

        // 2. Reference & Structural Data
        $this->call([
            CountiesTableSeeder::class,
            CountyGroupsSeeder::class,
            TaxonomySeeder::class,
            SystemFeatureSeeder::class,
            PlanSeeder::class,
            UserDataRetentionSettingsSeeder::class,
            DataDestructionCommandSeeder::class,
            SchedulerSettingsSeeder::class,
            SystemSettingsSeeder::class,
            SystemSettingsPublicProfileSeeder::class,
            ExchangeRateSeeder::class,
            EventManagementSeeder::class,
        ]);

        // 3. Essential Administrative Accounts
        $this->call([
            SystemAdminSeeder::class,
        ]);
    }

    /**
     * Seed optional demo content for development/staging.
     */
    protected function seedDemoContent(): void
    {
        $this->command->info('Seeding optional demo content...');

        $this->call([
            AdminUserSeeder::class, // Now repurposed for demo users
            SampleEventsSeeder::class,
            BlogPostsSeeder::class,
            BlogInteractionsSeeder::class,
            DonationCampaignSeeder::class,
            DonationSeeder::class,
            SamplePromoCodesSeeder::class,
            ForumCategoriesSeeder::class,
            ForumThreadsSeeder::class,
            ForumTagsSeeder::class,
            LabSpaceSeeder::class,
            LabBookingSeeder::class,
            LabMaintenanceSeeder::class,
            GroupMembersSeeder::class,
            PrivateGroupsSeeder::class,
            E2ETestSeeder::class,
        ]);
    }
}
