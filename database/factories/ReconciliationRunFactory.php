<?php

namespace Database\Factories;

use App\Models\ReconciliationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReconciliationRunFactory extends Factory
{
    protected $model = ReconciliationRun::class;

    public function definition(): array
    {
        return [
            'run_id' => Str::uuid(),
            'started_at' => now()->subHours(1),
            'completed_at' => now(),
            'status' => 'success',
            'period_start' => now()->subDay()->startOfDay(),
            'period_end' => now()->endOfDay(),
            'county' => $this->faker->optional()->randomElement(['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru']),
            'total_matched' => $this->faker->numberBetween(5, 50),
            'total_unmatched_app' => $this->faker->numberBetween(0, 5),
            'total_unmatched_gateway' => $this->faker->numberBetween(0, 5),
            'total_amount_mismatch' => $this->faker->numberBetween(0, 2),
            'total_app_amount' => $this->faker->randomFloat(2, 1000, 100000),
            'total_gateway_amount' => $this->faker->randomFloat(2, 1000, 100000),
            'total_discrepancy' => 0,
            'notes' => $this->faker->optional()->sentence(),
            'error_message' => null,
            'metadata' => ['source' => 'pesapal', 'manual' => false],
            'created_by' => User::factory(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'completed_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => 'Gateway API timeout',
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'partial',
            'total_unmatched_app' => $this->faker->numberBetween(5, 15),
            'total_unmatched_gateway' => $this->faker->numberBetween(5, 15),
        ]);
    }
}
