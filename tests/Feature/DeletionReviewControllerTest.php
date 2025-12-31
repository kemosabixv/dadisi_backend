<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tests for DeletionReviewController
 *
 * Tests staff approval/rejection of deletion requests
 * submitted by authors.
 */
class DeletionReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $staff;

    private User $regularUser;

    private Role $contentEditorRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Get or create content_editor role
        $this->contentEditorRole = Role::firstOrCreate(['name' => 'content_editor', 'guard_name' => 'web']);

        // Create test users
        $this->author = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->staff->assignRole($this->contentEditorRole);
        $this->regularUser = User::factory()->create();
    }

    /**
     * List Deletion Requests Tests
     */
    #[Test]
    public function staff_can_list_pending_deletion_requests(): void
    {
        // Create categories with pending deletion
        Category::factory()->count(2)->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        // Create tags with pending deletion
        Tag::factory()->count(1)->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        // Create non-pending items (should not appear)
        Category::factory()->create(['created_by' => $this->author->id]);
        Tag::factory()->create(['created_by' => $this->author->id]);

        $response = $this->actingAs($this->staff)
            ->getJson('/api/admin/blog/deletion-reviews');

        $response->assertJsonCount(3, 'data'); // 2 categories + 1 tag
    }

    #[Test]
    public function staff_can_filter_deletion_requests_by_type(): void
    {
        Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        Tag::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        // Filter by category only
        $response = $this->actingAs($this->staff)
            ->getJson('/api/admin/blog/deletion-reviews?type=category');

        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['type' => 'category']);
    }

    /**
     * Approve Deletion Tests
     */
    #[Test]
    public function staff_can_approve_category_deletion(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson("/api/admin/blog/deletion-reviews/category/{$category->id}/approve", [
                'comment' => 'Approved as requested.',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Category deleted successfully.']);

        // Category should be deleted
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    #[Test]
    public function staff_can_approve_tag_deletion(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson("/api/admin/blog/deletion-reviews/tag/{$tag->id}/approve");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Tag deleted successfully.']);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    #[Test]
    public function staff_cannot_approve_non_pending_category(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => null, // Not pending
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson("/api/admin/blog/deletion-reviews/category/{$category->id}/approve");

        $response->assertStatus(400);
    }

    /**
     * Reject Deletion Tests
     */
    #[Test]
    public function staff_can_reject_category_deletion(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson("/api/admin/blog/deletion-reviews/category/{$category->id}/reject", [
                'comment' => 'Category is still in use.',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Deletion request rejected.']);

        // Category should still exist with cleared deletion request
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertNull($category->fresh()->requested_deletion_at);
    }

    #[Test]
    public function staff_can_reject_tag_deletion(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson("/api/admin/blog/deletion-reviews/tag/{$tag->id}/reject");

        $response->assertStatus(200);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
        $this->assertNull($tag->fresh()->requested_deletion_at);
    }

    /**
     * Authorization Tests
     */
    #[Test]
    public function regular_user_cannot_access_deletion_reviews(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/admin/blog/deletion-reviews');

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_deletion_reviews(): void
    {
        $response = $this->getJson('/api/admin/blog/deletion-reviews');
        $response->assertStatus(401);
    }

    #[Test]
    public function regular_user_cannot_approve_deletion(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->author->id,
            'requested_deletion_at' => now(),
            'deletion_requested_by' => $this->author->id,
        ]);

        $response = $this->actingAs($this->regularUser)
            ->postJson("/api/admin/blog/deletion-reviews/category/{$category->id}/approve");

        $response->assertStatus(403);

        // Category should not be deleted
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    #[Test]
    public function approval_returns_404_for_nonexistent_item(): void
    {
        $response = $this->actingAs($this->staff)
            ->postJson('/api/admin/blog/deletion-reviews/category/99999/approve');

        $response->assertStatus(404);
    }

    #[Test]
    public function approval_returns_404_for_invalid_type(): void
    {
        $response = $this->actingAs($this->staff)
            ->postJson('/api/admin/blog/deletion-reviews/invalid/1/approve');

        $response->assertStatus(404);
    }
}
