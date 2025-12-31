<?php

namespace Database\Factories;

use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ForumPost>
 */
class ForumPostFactory extends Factory
{
    protected $model = ForumPost::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thread_id' => ForumThread::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->paragraphs(rand(2, 5), true),
            'is_edited' => $this->faker->boolean(20), // 20% chance of being edited
        ];
    }

    /**
     * Indicate that the post has been edited.
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
        ]);
    }

    /**
     * Create a post for a specific thread.
     */
    public function forThread(ForumThread $thread): static
    {
        return $this->state(fn (array $attributes) => [
            'thread_id' => $thread->id,
        ]);
    }

    /**
     * Create a post by a specific user.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
