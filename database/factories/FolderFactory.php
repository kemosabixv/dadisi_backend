<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => $this->faker->word,
            'root_type' => 'personal',
            'is_system' => false,
        ];
    }

    /**
     * Indicate that the folder is a system folder.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Indicate that the folder is a public folder.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'root_type' => 'public',
        ]);
    }
}
