<?php

namespace Database\Factories;

use App\Models\DonationCampaign;
use App\Models\County;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DonationCampaign>
 */
class DonationCampaignFactory extends Factory
{
    protected $model = DonationCampaign::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->faker->paragraphs(3, true),
            'short_description' => $this->faker->sentence(),
            'goal_amount' => $this->faker->randomFloat(2, 100000, 1000000),
            'minimum_amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'KES',
            'status' => 'active',
            'county_id' => County::inRandomOrder()->first()?->id ?? 1,
            'created_by' => User::factory(),
            'current_amount' => 0,
            'donor_count' => 0,
            'published_at' => now(),
            'starts_at' => now()->subDays(rand(1, 30)),
            'ends_at' => now()->addMonths(rand(1, 6)),
        ];
    }

    /**
     * Indicate that the campaign is draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the campaign is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
