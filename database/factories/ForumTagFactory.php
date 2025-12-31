<?php

namespace Database\Factories;

use App\Models\ForumTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ForumTag>
 */
class ForumTagFactory extends Factory
{
    protected $model = ForumTag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();
        
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'color' => $this->faker->hexColor(),
            'description' => $this->faker->sentence(),
        ];
    }
}
