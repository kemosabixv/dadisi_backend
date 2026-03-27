<?php

namespace Database\Factories;

use App\Models\LabMaintenanceBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabMaintenanceBlock>
 */
class LabMaintenanceBlockFactory extends Factory
{
    protected $model = LabMaintenanceBlock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $endsAt = (clone $startsAt)->modify('+2 hours');

        return [
            'lab_space_id' => \App\Models\LabSpace::factory(),
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
            'title' => $this->faker->sentence(),
            'reason' => $this->faker->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by' => \App\Models\User::factory(),
        ];
    }

    /**
     * Set block type to maintenance.
     */
    public function maintenance(): static
    {
        return $this->state([
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_MAINTENANCE,
        ]);
    }

    /**
     * Set block type to holiday.
     */
    public function holiday(): static
    {
        return $this->state([
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_HOLIDAY,
        ]);
    }

    /**
     * Set block type to closure.
     */
    public function closure(): static
    {
        return $this->state([
            'block_type' => LabMaintenanceBlock::BLOCK_TYPE_CLOSURE,
        ]);
    }
}
