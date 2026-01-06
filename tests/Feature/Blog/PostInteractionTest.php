<?php

namespace Tests\Feature\Blog;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected User $admin;
    protected Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for permission tests
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->post = Post::factory()->create([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    // ==================== COMMENT TESTS ====================

    public function test_authenticated_user_can_comment_on_post(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/blog/posts/{$this->post->slug}/comments",
            ['body' => 'This is a great post!']
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Comment added successfully.',
            ]);

        $this->assertDatabaseHas('comments', [
            'user_id' => $this->user->id,
            'commentable_type' => Post::class,
            'commentable_id' => $this->post->id,
            'body' => 'This is a great post!',
        ]);
    }

    public function test_guest_cannot_comment(): void
    {
        $response = $this->postJson(
            "/api/blog/posts/{$this->post->slug}/comments",
            ['body' => 'This should fail']
        );

        $response->assertStatus(401);
    }

    public function test_user_can_delete_own_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->user->id,
            'commentable_type' => Post::class,
            'commentable_id' => $this->post->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(
            "/api/blog/comments/{$comment->id}"
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_others_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->otherUser->id,
            'commentable_type' => Post::class,
            'commentable_id' => $this->post->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson(
            "/api/blog/comments/{$comment->id}"
        );

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_any_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->otherUser->id,
            'commentable_type' => Post::class,
            'commentable_id' => $this->post->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson(
            "/api/blog/comments/{$comment->id}"
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_can_list_comments_for_post(): void
    {
        Comment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'commentable_type' => Post::class,
            'commentable_id' => $this->post->id,
        ]);

        $response = $this->getJson("/api/blog/posts/{$this->post->slug}/comments");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data.data');
    }

    // ==================== LIKE TESTS ====================

    public function test_authenticated_user_can_like_post(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/blog/posts/{$this->post->slug}/like",
            ['type' => 'like']
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'action' => 'added',
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'likeable_type' => Post::class,
            'likeable_id' => $this->post->id,
            'type' => 'like',
        ]);
    }

    public function test_authenticated_user_can_dislike_post(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/blog/posts/{$this->post->slug}/like",
            ['type' => 'dislike']
        );

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'action' => 'added',
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'type' => 'dislike',
        ]);
    }

    public function test_liking_twice_toggles_off(): void
    {
        // First like
        $this->actingAs($this->user)->postJson(
            "/api/blog/posts/{$this->post->slug}/like",
            ['type' => 'like']
        );

        // Second like (toggle off)
        $response = $this->actingAs($this->user)->postJson(
            "/api/blog/posts/{$this->post->slug}/like",
            ['type' => 'like']
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'action' => 'removed',
            ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $this->user->id,
            'likeable_id' => $this->post->id,
        ]);
    }

    public function test_switching_vote_type(): void
    {
        // Initial like
        $this->actingAs($this->user)->postJson(
            "/api/blog/posts/{$this->post->slug}/like",
            ['type' => 'like']
        );

        // Switch to dislike
        $response = $this->actingAs($this->user)->postJson(
            "/api/blog/posts/{$this->post->slug}/like",
            ['type' => 'dislike']
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'action' => 'switched',
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'type' => 'dislike',
        ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $this->user->id,
            'type' => 'like',
        ]);
    }

    public function test_guest_cannot_like(): void
    {
        $response = $this->postJson(
            "/api/blog/posts/{$this->post->slug}/like",
            ['type' => 'like']
        );

        $response->assertStatus(401);
    }
}
