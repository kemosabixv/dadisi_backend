<?php

namespace Database\Factories;

use App\Models\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventCategoryFactory extends Factory
{
    protected $model = EventCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'color' => $this->faker->safeHexColor(),
            'image_path' => null,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 100),
            'parent_id' => null,
        ];
    }
}
