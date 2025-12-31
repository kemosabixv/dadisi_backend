<?php

namespace Database\Factories;

use App\Models\Donation;
use App\Models\User;
use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonationFactory extends Factory
{
    protected $model = Donation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'donor_name' => $this->faker->name(),
            'donor_email' => $this->faker->unique()->safeEmail(),
            'donor_phone' => $this->faker->phoneNumber(),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'currency' => 'KES',
            'status' => 'pending',
            'reference' => Donation::generateReference(),
            'county_id' => County::inRandomOrder()->first()?->id ?? County::factory(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'receipt_number' => Donation::generateReceiptNumber(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}
