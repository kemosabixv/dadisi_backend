<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use Spatie\Permission\Models\Role;

/**
 * Tests for AuthorBlogController
 *
 * Tests author CRUD operations for categories and tags,
 * including the deletion request workflow.
 */
class AuthorBlogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->author = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /**
     * Categories Tests
     */

    #[Test]
    public function author_can_list_their_own_categories(): void
    {
        // Create categories owned by author
        Category::factory()->count(3)->create(['created_by' => $this->author->id]);

        // Create categories owned by other user (should also appear - authors see all)
        Category::factory()->count(2)->create(['created_by' => $this->otherUser->id]);

        $response = $this->actingAs($this->author)
            ->getJson('/api/user/blog/categories');

        $response->assertStatus(200);
        // Authors see ALL categories (3 + 2 = 5) for selection when tagging posts
        $response->assertJsonCount(5, 'data');
    }

    #[Test]
    public function author_can_create_category(): void
    {
        $response = $this->actingAs($this->author)
            ->postJson('/api/user/blog/categories', [
                'name' => 'My New Category',
                'description' => 'A category I created',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'My New Category']);

        $this->assertDatabaseHas('categories', [
            'name' => 'My New Category',
            'created_by' => $this->author->id,
        ]);
    }

    #[Test]
    public function author_cannot_create_category_with_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Existing Category']);

        $response = $this->actingAs($this->author)
            ->postJson('/api/user/blog/categories', [
                'name' => 'Existing Category',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function author_can_update_their_own_category(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($this->author)
            ->putJson("/api/user/blog/categories/{$category->id}", [
                'name' => 'Updated Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Name']);
    }

    #[Test]
    public function author_cannot_update_other_users_category(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->author)
            ->putJson("/api/user/blog/categories/{$category->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function author_cannot_update_category_pending_deletion(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
        ]);

        $response = $this->actingAs($this->author)
            ->putJson("/api/user/blog/categories/{$category->id}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function author_can_request_deletion_of_their_category(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => null,
        ]);

        $response = $this->actingAs($this->author)
            ->postJson("/api/user/blog/categories/{$category->id}/request-delete");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Deletion request submitted for staff review.']);

        $this->assertNotNull($category->fresh()->requested_deletion_at);
        $this->assertEquals($this->author->id, $category->fresh()->deletion_requested_by);
    }

    #[Test]
    public function author_cannot_request_deletion_of_other_users_category(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->author)
            ->postJson("/api/user/blog/categories/{$category->id}/request-delete");

        $response->assertStatus(403);
    }

    #[Test]
    public function author_cannot_request_deletion_twice(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
        ]);

        $response = $this->actingAs($this->author)
            ->postJson("/api/user/blog/categories/{$category->id}/request-delete");

        $response->assertStatus(400);
    }

    /**
     * Tags Tests
     */

    #[Test]
    public function author_can_list_their_own_tags(): void
    {
        Tag::factory()->count(4)->create(['created_by' => $this->author->id]);
        Tag::factory()->count(2)->create(['created_by' => $this->otherUser->id]);

        $response = $this->actingAs($this->author)
            ->getJson('/api/user/blog/tags');

        $response->assertStatus(200);
        // Authors see ALL tags (4 + 2 = 6) for selection when tagging posts
        $response->assertJsonCount(6, 'data');
    }

    #[Test]
    public function author_can_create_tag(): void
    {
        $response = $this->actingAs($this->author)
            ->postJson('/api/user/blog/tags', [
                'name' => 'My New Tag',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['name' => 'My New Tag']);

        $this->assertDatabaseHas('tags', [
            'name' => 'My New Tag',
            'created_by' => $this->author->id,
        ]);
    }

    #[Test]
    public function author_can_update_their_own_tag(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->author->id,
            'name' => 'Original Tag',
        ]);

        $response = $this->actingAs($this->author)
            ->putJson("/api/user/blog/tags/{$tag->id}", [
                'name' => 'Updated Tag',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Updated Tag']);
    }

    #[Test]
    public function author_cannot_update_other_users_tag(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->author)
            ->putJson("/api/user/blog/tags/{$tag->id}", [
                'name' => 'Hacked Tag',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function author_can_request_deletion_of_their_tag(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => null,
        ]);

        $response = $this->actingAs($this->author)
            ->postJson("/api/user/blog/tags/{$tag->id}/request-delete");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Deletion request submitted for staff review.']);

        $this->assertNotNull($tag->fresh()->requested_deletion_at);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_author_endpoints(): void
    {
        $response = $this->getJson('/api/user/blog/categories');
        $response->assertStatus(401);

        $response = $this->getJson('/api/user/blog/tags');
        $response->assertStatus(401);
    }
}
