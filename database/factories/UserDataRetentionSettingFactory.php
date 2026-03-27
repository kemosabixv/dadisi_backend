<?php

namespace Database\Factories;

use App\Models\UserDataRetentionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserDataRetentionSetting>
 */
class UserDataRetentionSettingFactory extends Factory
{
    protected $model = UserDataRetentionSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_type' => fake()->unique()->word(),
            'retention_days' => fake()->numberBetween(1, 365),
            'retention_minutes' => null,
            'auto_delete' => true,
            'description' => fake()->sentence(),
        ];
    }
}
