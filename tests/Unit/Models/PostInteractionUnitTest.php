<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostInteractionUnitTest extends TestCase
{
    use RefreshDatabase;

    // ==================== RELATIONSHIP TESTS ====================

    public function test_post_has_comments(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
        ]);

        $this->assertTrue($post->comments->contains($comment));
        $this->assertInstanceOf(Comment::class, $post->comments->first());
    }

    public function test_post_has_likes(): void
    {
        $post = Post::factory()->create();
        $like = Like::factory()->create([
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
        ]);

        $this->assertTrue($post->likes->contains($like));
        $this->assertInstanceOf(Like::class, $post->likes->first());
    }

    public function test_comment_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $comment->user->id);
    }

    public function test_like_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $like = Like::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $like->user->id);
    }

    // ==================== LOGIC TESTS ====================

    public function test_like_model_scopes(): void
    {
        $post = Post::factory()->create();

        Like::factory()->count(3)->create([
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
            'type' => 'like',
        ]);

        Like::factory()->count(2)->create([
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
            'type' => 'dislike',
        ]);

        $this->assertEquals(3, $post->likes()->likes()->count());
        $this->assertEquals(2, $post->likes()->dislikes()->count());
    }

    public function test_post_likes_count_attribute(): void
    {
        $post = Post::factory()->create();

        Like::factory()->count(5)->create([
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
            'type' => 'like',
        ]);

        Like::factory()->count(2)->create([
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
            'type' => 'dislike',
        ]);

        $this->assertEquals(5, $post->fresh()->likes_count);
        $this->assertEquals(2, $post->fresh()->dislikes_count);
    }

    public function test_post_comments_count_attribute(): void
    {
        $post = Post::factory()->create();

        Comment::factory()->count(4)->create([
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
        ]);

        $this->assertEquals(4, $post->fresh()->comments_count);
    }

    public function test_comment_replies_relationship(): void
    {
        $post = Post::factory()->create();
        $parentComment = Comment::factory()->create([
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
        ]);

        $reply = Comment::factory()->create([
            'commentable_type' => Post::class,
            'commentable_id' => $post->id,
            'parent_id' => $parentComment->id,
        ]);

        $this->assertTrue($parentComment->replies->contains($reply));
        $this->assertEquals($parentComment->id, $reply->parent->id);
    }
}
