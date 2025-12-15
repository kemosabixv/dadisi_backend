<?php

namespace Database\Factories;

use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReconciliationItemFactory extends Factory
{
    protected $model = ReconciliationItem::class;

    public function definition(): array
    {
        return [
            'reconciliation_run_id' => ReconciliationRun::factory(),
            'transaction_id' => 'TXN_' . $this->faker->numerify('##########'),
            'reference' => 'REF_' . $this->faker->numerify('########'),
            'source' => $this->faker->randomElement(['app', 'gateway']),
            'transaction_date' => $this->faker->dateTimeBetween('-7 days'),
            'amount' => $this->faker->randomFloat(2, 10, 10000),
            'payer_name' => $this->faker->optional()->name(),
            'payer_phone' => $this->faker->optional()->numerify('254#########'),
            'payer_email' => $this->faker->optional()->email(),
            'county' => $this->faker->optional()->randomElement(['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru']),
            'app_status' => $this->faker->randomElement(['completed', 'pending', 'failed']),
            'gateway_status' => $this->faker->randomElement(['completed', 'pending', 'failed']),
            'reconciliation_status' => 'matched',
            'match_reference' => null,
            'discrepancy_amount' => null,
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => [],
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'reconciliation_status' => 'matched',
            'match_reference' => 'REF_' . $this->faker->numerify('########'),
        ]);
    }

    public function unmatchedApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'reconciliation_status' => 'unmatched_app',
            'source' => 'app',
        ]);
    }

    public function unmatchedGateway(): static
    {
        return $this->state(fn (array $attributes) => [
            'reconciliation_status' => 'unmatched_gateway',
            'source' => 'gateway',
        ]);
    }

    public function amountMismatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'reconciliation_status' => 'amount_mismatch',
            'discrepancy_amount' => abs($this->faker->randomFloat(2, 10, 500)),
        ]);
    }
}
