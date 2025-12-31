<?php

namespace Database\Factories;

use App\Models\LabSpace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabSpace>
 */
class LabSpaceFactory extends Factory
{
    protected $model = LabSpace::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Innovation Lab',
            'Maker Space',
            'Tech Hub',
            'Co-Working Space',
            'Digital Studio',
            'Prototyping Lab',
            'Research Center',
            'Community Workshop',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name . '-' . fake()->unique()->numberBetween(1, 1000)),
            'description' => fake()->paragraph(),
            'capacity' => fake()->numberBetween(1, 20),
            'county' => fake()->randomElement(['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika']),
            'type' => fake()->randomElement(['dry_lab', 'wet_lab', 'makerspace', 'workshop', 'studio', 'other']),
            'image_path' => fake()->optional()->imageUrl(640, 480, 'lab', true),
            'safety_requirements' => fake()->optional()->text(200),
            'is_available' => true,
            'available_from' => '08:00',
            'available_until' => '18:00',
            'equipment_list' => fake()->randomElements([
                '3D Printer',
                'Laser Cutter',
                'CNC Machine',
                'Workstations',
                'Soldering Stations',
                'Electronics Kit',
                'Power Tools',
                'Meeting Room',
                'Whiteboard',
                'Projector',
            ], fake()->numberBetween(3, 6)),
            'rules' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Indicate that the lab space is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }

    /**
     * Indicate that the lab space has limited capacity.
     */
    public function limitedCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Set a specific county for the lab space.
     */
    public function inCounty(string $county): static
    {
        return $this->state(fn (array $attributes) => [
            'county' => $county,
        ]);
    }
}
