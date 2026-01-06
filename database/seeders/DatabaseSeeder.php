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
        // Seed roles and permissions first
        $this->call([
            RolesPermissionsSeeder::class,
        ]);

        // Seed reference data
        $this->call([
            CountiesTableSeeder::class,
            SystemFeatureSeeder::class,
            PlanSeeder::class,
            UserDataRetentionSettingsSeeder::class,
            SchedulerSettingsSeeder::class,
            ExchangeRateSeeder::class,
            AdminUserSeeder::class,
            EventManagementSeeder::class,
            SampleEventsSeeder::class,
            SampleAuthorsSeeder::class,
            BlogPostsSeeder::class,
            BlogInteractionsSeeder::class,
            DonationCampaignSeeder::class,
            DonationSeeder::class,
            SampleRegistrationsSeeder::class,
            SamplePromoCodesSeeder::class,
            ForumCategoriesSeeder::class,
            CountyGroupsSeeder::class,
            TaxonomySeeder::class,
            ForumThreadsSeeder::class,
            ForumTagsSeeder::class,
            LabSpaceSeeder::class,
            GroupMembersSeeder::class,
        ]);

        // Optionally seed a demo user
        // User::factory(1)->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
