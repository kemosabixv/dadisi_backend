<?php

namespace Database\Seeders;

use Database\Seeders\CountiesTableSeeder;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed reference data first
        $this->call([
            CountiesTableSeeder::class,
        ]);

        // Optionally seed a demo user
        // User::factory(1)->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
