<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\County;
use App\Models\ForumTag;
use App\Models\ForumThread;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AdminForumBlogReviewTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $author;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->seed(RolesPermissionsSeeder::class);

        // Create admin user
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->admin->assignRole(Role::where('name', 'admin')->first());
        $this->admin = $this->admin->fresh();

        // Create author user
        $this->author = User::factory()->create(['email' => 'author@example.com']);
        $this->author->assignRole(Role::where('name', 'member')->first());
        $this->author = $this->author->fresh();
    }

    #[Test]
    public function admin_can_create_global_forum_group()
    {
        $payload = [
            'name' => 'Global Science Group',
            'description' => 'A group for science lovers worldwide.',
            'county_id' => null,
            'is_active' => true,
            'is_private' => false,
        ];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson('/api/admin/groups', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', [
            'name' => 'Global Science Group',
            'county_id' => null,
            'is_active' => true,
            'is_private' => false,
        ]);
    }

    #[Test]
    public function admin_can_preview_affected_posts_for_category_deletion()
    {
        // Create category and post
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        $post = Post::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'Affected Test Post',
        ]);
        $post->categories()->attach($category->id);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/api/admin/blog/deletion-reviews/category/{$category->id}/affected");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Affected Test Post']);
    }

    #[Test]
    public function admin_can_preview_affected_threads_for_forum_tag_deletion()
    {
        // Create forum tag and thread
        $tag = ForumTag::factory()->create([
            'name' => 'Research',
            'slug' => 'research',
        ]);

        $thread = ForumThread::factory()->create([
            'title' => 'Important Physics Thread',
            'user_id' => $this->author->id,
        ]);
        
        $thread->tags()->attach($tag->id);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/api/forum/tags/{$tag->slug}/affected-threads");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Important Physics Thread']);
    }

    #[Test]
    public function approving_deletion_review_sends_notification()
    {
        // Create category with deletion request
        $category = Category::factory()->create([
            'name' => 'Old Category',
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        $payload = ['comment' => 'Approved as requested.'];

        $response = $this->actingAs($this->admin, 'web')
            ->postJson("/api/admin/blog/deletion-reviews/category/{$category->id}/approve", $payload);

        $response->assertStatus(200);
        
        // Verify notification exists for the author
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->author->id,
            'type' => 'App\Notifications\DeletionRequestProcessed',
        ]);
        
        // Verify item is actually deleted
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
