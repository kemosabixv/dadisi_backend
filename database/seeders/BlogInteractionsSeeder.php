<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogInteractionsSeeder extends Seeder
{
    /**
     * Seed sample comments and likes for blog posts.
     */
    public function run(): void
    {
        // Clear existing blog interactions to avoid duplicates on re-seed
        Comment::where('commentable_type', Post::class)->delete();
        Like::where('likeable_type', Post::class)->delete();

        $posts = Post::where('status', 'published')->get();
        $users = User::take(10)->get();

        if ($posts->isEmpty() || $users->isEmpty()) {
            $this->command->warn('No posts or users found. Skipping blog interactions seeding.');
            return;
        }

        $sampleComments = [
            'Great article! Very informative.',
            'Thanks for sharing this research.',
            'This is exactly what I was looking for.',
            'Interesting perspective on the topic.',
            'Well written and easy to understand.',
            'I learned something new today!',
            'Could you elaborate more on the methodology?',
            'This will help with my research project.',
            'Excellent breakdown of complex concepts.',
            'Looking forward to more articles like this.',
        ];

        $commentCount = 0;
        $likeCount = 0;

        foreach ($posts as $post) {
            // Add 2-5 random comments per post
            $numComments = rand(2, 5);
            $shuffledUsers = $users->shuffle();

            for ($i = 0; $i < $numComments && $i < $shuffledUsers->count(); $i++) {
                Comment::create([
                    'user_id' => $shuffledUsers[$i]->id,
                    'commentable_type' => Post::class,
                    'commentable_id' => $post->id,
                    'body' => $sampleComments[array_rand($sampleComments)],
                ]);
                $commentCount++;
            }

            // Add random likes/dislikes (more likes than dislikes)
            foreach ($users->shuffle()->take(rand(3, 8)) as $user) {
                // 80% chance of like, 20% chance of dislike
                $type = rand(1, 10) <= 8 ? 'like' : 'dislike';

                Like::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'likeable_type' => Post::class,
                        'likeable_id' => $post->id,
                    ],
                    ['type' => $type]
                );
                $likeCount++;
            }
        }

        $this->command->info("Created {$commentCount} comments and {$likeCount} likes/dislikes on blog posts.");
    }
}
