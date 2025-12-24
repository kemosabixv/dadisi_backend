<?php

namespace Database\Factories;

use App\Models\Refund;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'refundable_type' => 'App\\Models\\EventOrder',
            'refundable_id' => 1,
            'payment_id' => Payment::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 1000),
            'currency' => 'KES',
            'original_amount' => fn(array $attrs) => $attrs['amount'],
            'status' => Refund::STATUS_PENDING,
            'reason' => $this->faker->randomElement([
                Refund::REASON_CANCELLATION,
                Refund::REASON_CUSTOMER_REQUEST,
                Refund::REASON_DUPLICATE,
            ]),
            'customer_notes' => $this->faker->optional()->sentence(),
            'requested_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => Refund::STATUS_PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => Refund::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => Refund::STATUS_COMPLETED,
            'completed_at' => now(),
            'gateway_refund_id' => 'TEST-' . uniqid(),
        ]);
    }
}
