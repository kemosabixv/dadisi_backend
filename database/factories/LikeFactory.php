<?php

namespace Database\Factories;

use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Like>
 */
class LikeFactory extends Factory
{
    protected $model = Like::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'likeable_type' => Post::class,
            'likeable_id' => Post::factory(),
            'type' => 'like',
        ];
    }

    /**
     * Create a dislike.
     */
    public function dislike(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dislike',
        ]);
    }
}
