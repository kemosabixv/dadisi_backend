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
            'county_id' => County::inRandomOrder()->first()?->id ?? County::factory(),
            'title' => $title,
            'slug' => null,  // Will be generated in afterMaking
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
            // Only regenerate slug if it's empty or if we're creating from scratch
            // Don't override explicitly set slugs
            if (empty($post->getAttribute('slug'))) {
                if ($post->title) {
                    $baseSlug = Str::slug($post->title);
                    $slug = $baseSlug;
                    $counter = 1;
                    
                    // Check if slug already exists and make it unique
                    while (Post::where('slug', $slug)->exists()) {
                        $slug = $baseSlug . '-' . $counter;
                        $counter++;
                    }
                    
                    $post->slug = $slug;
                }
            }
            
            // If published but published_at is null, set it to now
            if ($post->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        })->afterCreating(function (Post $post) {
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

    public function draft(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'draft',
                'published_at' => null,
            ];
        });
    }

    public function deleted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'deleted_at' => now(),
            ];
        });
    }
}
