<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        $startsAt = $this->faker->dateTimeBetween('+1 week', '+3 months');
        $endsAt = (clone $startsAt)->modify('+2 hours');

        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . uniqid(),
            'description' => $this->faker->paragraphs(3, true),
            'venue' => $this->faker->address(),
            'is_online' => $this->faker->boolean(30),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $this->faker->optional()->numberBetween(20, 200),
            'price' => $this->faker->randomFloat(2, 0, 5000),
            'currency' => 'KES',
            'status' => 'published',
            'event_type' => $this->faker->randomElement(['workshop', 'meetup', 'conference', 'webinar']),
            'organizer_id' => User::factory(),
            'created_by' => fn(array $attrs) => $attrs['organizer_id'],
        ];
    }

    public function draft(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'draft',
        ]);
    }

    public function published(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'published',
        ]);
    }

    public function free(): static
    {
        return $this->state(fn(array $attrs) => [
            'price' => 0,
        ]);
    }

    public function online(): static
    {
        return $this->state(fn(array $attrs) => [
            'is_online' => true,
            'venue' => null,
        ]);
    }
}
