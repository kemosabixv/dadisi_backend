<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use App\Models\LabBooking;
use App\Models\LabSpace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AttendanceLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['attended', 'no_show', 'pending'];

        return [
            'booking_id' => LabBooking::factory(),
            'lab_id' => LabSpace::factory(),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement($statuses),
            'check_in_time' => $this->faker->optional()->dateTime(),
            'marked_by_id' => User::factory(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the attendance log is attended.
     */
    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'attended',
            'check_in_time' => now(),
        ]);
    }

    /**
     * Indicate that the attendance log is a no-show.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_show',
        ]);
    }

    /**
     * Indicate that the attendance log is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'check_in_time' => null,
        ]);
    }

    /**
     * Indicate for a guest user (no user_id).
     */
    public function forGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
