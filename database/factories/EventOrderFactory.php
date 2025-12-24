<?php

namespace Database\Factories;

use App\Models\EventOrder;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventOrderFactory extends Factory
{
    protected $model = EventOrder::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->randomFloat(2, 500, 5000);
        $originalAmount = $quantity * $unitPrice;

        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'original_amount' => $originalAmount,
            'promo_discount' => 0,
            'subscriber_discount' => 0,
            'total_amount' => $originalAmount,
            'currency' => 'KES',
            'status' => 'pending',
            'attendee_name' => $this->faker->name(),
            'attendee_email' => $this->faker->safeEmail(),
            'attendee_phone' => $this->faker->phoneNumber(),
            'qr_code_token' => Str::random(32),
            'order_reference' => 'ORD-' . strtoupper(Str::random(10)),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'paid',
            'purchased_at' => now(),
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn(array $attrs) => [
            'user_id' => null,
            'is_guest' => true,
        ]);
    }

    public function withPromoDiscount(float $percent): static
    {
        return $this->state(function (array $attrs) use ($percent) {
            $discount = $attrs['original_amount'] * ($percent / 100);
            return [
                'promo_discount' => $discount,
                'total_amount' => $attrs['original_amount'] - $discount - ($attrs['subscriber_discount'] ?? 0),
            ];
        });
    }
}
