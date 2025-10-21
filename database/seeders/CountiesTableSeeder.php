<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountiesTableSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $counties = [
            ['name' => 'Mombasa'],
            ['name' => 'Kwale'],
            ['name' => 'Kilifi'],
            ['name' => 'Tana River'],
            ['name' => 'Lamu'],
            ['name' => 'Taita-Taveta'],
            ['name' => 'Garissa'],
            ['name' => 'Wajir'],
            ['name' => 'Mandera'],
            ['name' => 'Marsabit'],
            ['name' => 'Isiolo'],
            ['name' => 'Meru'],
            ['name' => 'Tharaka-Nithi'],
            ['name' => 'Embu'],
            ['name' => 'Kitui'],
            ['name' => 'Machakos'],
            ['name' => 'Makueni'],
            ['name' => 'Nyandarua'],
            ['name' => 'Nyeri'],
            ['name' => 'Kirinyaga'],
            ['name' => 'Murang\'a'],
            ['name' => 'Kiambu'],
            ['name' => 'Turkana'],
            ['name' => 'West Pokot'],
            ['name' => 'Samburu'],
            ['name' => 'Trans Nzoia'],
            ['name' => 'Uasin Gishu'],
            ['name' => 'Elgeyo-Marakwet'],
            ['name' => 'Nandi'],
            ['name' => 'Baringo'],
            ['name' => 'Laikipia'],
            ['name' => 'Nakuru'],
            ['name' => 'Narok'],
            ['name' => 'Kajiado'],
            ['name' => 'Kericho'],
            ['name' => 'Bomet'],
            ['name' => 'Kakamega'],
            ['name' => 'Vihiga'],
            ['name' => 'Bungoma'],
            ['name' => 'Busia'],
            ['name' => 'Siaya'],
            ['name' => 'Kisumu'],
            ['name' => 'Homa Bay'],
            ['name' => 'Migori'],
            ['name' => 'Kisii'],
            ['name' => 'Nyamira'],
            ['name' => 'Nairobi City'],
        ];

        DB::table('counties')->insert(array_map(function ($c) use ($now) {
            return $c + ['created_at' => $now, 'updated_at' => $now];
        }, $counties));
    }
}

