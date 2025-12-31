<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'code' => strtoupper(Str::random(8)),
            'discount_type' => $this->faker->randomElement(['percentage', 'fixed']),
            'discount_value' => $this->faker->randomFloat(2, 5, 50),
            'usage_limit' => $this->faker->optional()->numberBetween(10, 100),
            'used_count' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the promo code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the promo code is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the promo code is expired.
     */
    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'valid_until' => now()->subDay(),
        ]);
    }

    /**
     * Indicate usage limit is reached.
     */
    public function usageLimitReached(): static
    {
        return $this->state(fn(array $attributes) => [
            'usage_limit' => 1,
            'used_count' => 1,
        ]);
    }

    /**
     * Set a specific code.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn(array $attributes) => [
            'code' => $code,
        ]);
    }

    /**
     * Set percentage discount type.
     */
    public function percentage(float $value = 10): static
    {
        return $this->state(fn(array $attributes) => [
            'discount_type' => 'percentage',
            'discount_value' => $value,
        ]);
    }

    /**
     * Set fixed discount type.
     */
    public function fixed(float $value = 100): static
    {
        return $this->state(fn(array $attributes) => [
            'discount_type' => 'fixed',
            'discount_value' => $value,
        ]);
    }
}
