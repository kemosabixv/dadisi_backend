<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payable_type' => 'App\\Models\\EventOrder',
            'payable_id' => 1,
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'currency' => 'KES',
            'status' => 'pending',
            'gateway' => 'mock',
            'external_reference' => 'PAY-' . strtoupper($this->faker->unique()->bothify('########')),
            'order_reference' => 'ORDER-' . strtoupper($this->faker->unique()->bothify('########')),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => 'TXN-' . strtoupper($this->faker->bothify('########')),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'failed',
        ]);
    }
}
