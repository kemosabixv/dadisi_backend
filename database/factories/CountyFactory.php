<?php

namespace Database\Factories;

use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountyFactory extends Factory
{
    protected $model = County::class;

    public function definition(): array
    {
        $counties = [
            'Mombasa', 'Kwale', 'Kilifi', 'Tana River', 'Lamu', 'Taita Taveta',
            'Garissa', 'Wajir', 'Mandera', 'Marsabit', 'Isiolo', 'Meru',
            'Tharaka Nithi', 'Embu', 'Kitui', 'Machakos', 'Makueni', 'Nairobi',
            'Kiambu', 'Muranga', 'Nyeri', 'Kirinyaga', 'Samburu', 'Baringo',
            'West Pokot', 'Uasin Gishu', 'Elgeyo Marakwet', 'Nandi', 'Kisumu',
            'Siaya', 'Kericho', 'Bomet', 'Kakamega', 'Vihiga', 'Bungoma',
            'Busia', 'Nakuru', 'Narok', 'Kajiado', 'Kericho',
        ];

        return [
            'name' => $this->faker->unique()->randomElement($counties),
        ];
    }
}
