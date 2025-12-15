<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Models\County;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'user_id' => User::factory(),
            'county_id' => County::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => $this->faker->paragraph(),
            'body' => $this->faker->paragraphs(5, true),
            'status' => 'draft',
            'published_at' => null,
            'hero_image_path' => null,
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (Post $post) {
            // Ensure slug matches title if it was customized
            if ($post->title) {
                $slug = Str::slug($post->title);
                // If slug already exists, append a random suffix
                if (Post::where('slug', $slug)->where('id', '!=', $post->id ?? 0)->exists()) {
                    $slug .= '-' . Str::random(8);
                }
                $post->slug = $slug;
            }
            // If status is published but published_at is null, set it to now
            if ($post->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        })->afterCreating(function (Post $post) {
            // Ensure slug matches title in database after creation and is unique
            if ($post->title) {
                $slug = Str::slug($post->title);
                // If slug is not unique, append a random suffix
                if (Post::where('slug', $slug)->where('id', '!=', $post->id)->exists()) {
                    $slug .= '-' . Str::random(8);
                    $post->slug = $slug;
                }
            }
            // If status is published but published_at is null, set it to now
            if ($post->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
            // Save if any changes were made
            if ($post->isDirty()) {
                $post->save();
            }
        });
    }

    public function published(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'published',
                'published_at' => now(),
            ];
        });
    }

    public function featured(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_featured' => true,
            ];
        });
    }
}
