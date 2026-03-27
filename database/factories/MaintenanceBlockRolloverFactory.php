<?php

namespace Database\Factories;

use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\MaintenanceBlockRollover;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceBlockRollover>
 */
class MaintenanceBlockRolloverFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MaintenanceBlockRollover::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = [
            MaintenanceBlockRollover::STATUS_INITIATED,
            MaintenanceBlockRollover::STATUS_PENDING_USER,
            MaintenanceBlockRollover::STATUS_ESCALATED,
            MaintenanceBlockRollover::STATUS_ROLLED_OVER,
            MaintenanceBlockRollover::STATUS_CANCELLED,
        ];

        return [
            'maintenance_block_id' => LabMaintenanceBlock::factory(),
            'original_booking_id' => LabBooking::factory(),
            'rolled_over_booking_id' => null,
            'status' => $this->faker->randomElement($statuses),
            'original_booking_data' => null,
            'rejection_reason' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the rollover is pending user resolution.
     */
    public function pendingUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceBlockRollover::STATUS_PENDING_USER,
            'rolled_over_booking_id' => null,
        ]);
    }

    /**
     * Indicate that the rollover is completed.
     */
    public function rolledOver(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceBlockRollover::STATUS_ROLLED_OVER,
            'rolled_over_booking_id' => LabBooking::factory(),
        ]);
    }

    /**
     * Indicate that the rollover is escalated.
     */
    public function escalated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceBlockRollover::STATUS_ESCALATED,
        ]);
    }

    /**
     * Indicate that the rollover is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaintenanceBlockRollover::STATUS_CANCELLED,
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }
}
