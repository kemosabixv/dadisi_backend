<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CountyGroupsSeeder extends Seeder
{
    /**
     * Seed county groups from existing counties.
     */
    public function run(): void
    {
        $counties = County::all();

        foreach ($counties as $county) {
            Group::firstOrCreate(
                ['county_id' => $county->id],
                [
                    'name' => $county->name . ' Community',
                    'slug' => Str::slug($county->name . '-community'),
                    'description' => 'Connect with members from ' . $county->name . ' county. Share local events, news, and discussions.',
                    'is_private' => false,
                    'is_active' => true,
                    'member_count' => 0,
                ]
            );
        }

        $this->command->info('Created ' . $counties->count() . ' county groups.');
    }
}
