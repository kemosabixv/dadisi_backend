<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingsPublicProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SystemSetting::updateOrCreate(
            ['key' => 'membership_page_user_list_enabled'],
            [
                'value' => app()->isProduction() ? 'false' : 'true',
                'group' => 'membership',
                'type' => 'boolean',
                'description' => 'Display a list of public members on the membership plans page',
                'is_public' => true,
            ]
        );
    }
}
