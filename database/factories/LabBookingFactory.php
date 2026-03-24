<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabBooking>
 */
class LabBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_space_id' => \App\Models\LabSpace::factory(),
            'user_id' => \App\Models\User::factory(),
            'title' => $this->faker->sentence(),
            'purpose' => $this->faker->paragraph(),
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(1)->addHours(2),
            'slot_type' => 'hourly',
            'status' => 'pending',
            'quota_consumed' => false,
        ];
    }
}
