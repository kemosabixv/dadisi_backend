<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 500, 5000),
            'currency' => 'KES',
            'quantity' => 100,
            'available' => 100,
            'order_limit' => 10,
            'is_active' => true,
        ];
    }
}
