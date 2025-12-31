<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTag;
use App\Models\ForumThread;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ForumEndpointsTest
 *
 * Tests forum API endpoints covering:
 * - Public access (guests)
 * - Subscriber access (threads, posts, categories, tags)
 * - Staff moderation
 * - Filtering by category/tag
 */
class ForumEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;
    private User $subscriber;
    private User $staffUser;
    private ForumCategory $category;
    private ForumCategory $otherCategory;
    private ForumTag $tag;
    private ForumTag $otherTag;
    private ForumThread $thread;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->guest = User::factory()->create();
        $this->subscriber = User::factory()->create();
        $this->staffUser = User::factory()->create();

        // Create member profiles
        $county = \App\Models\County::factory()->create();
        
        $this->subscriber->memberProfile()->create([
            'first_name' => 'Subscriber',
            'last_name' => 'User',
            'county_id' => $county->id,
        ]);
        
        $this->staffUser->memberProfile()->create([
            'first_name' => 'Staff',
            'last_name' => 'User',
            'county_id' => $county->id,
            'is_staff' => true,
        ]);

        // Create subscription for subscriber
        $plan = Plan::factory()->create();
        
        // Ensure forum features exist and are associated with the plan
        $threadFeature = \App\Models\SystemFeature::firstOrCreate(
            ['slug' => 'forum_thread_limit'],
            ['name' => 'Forum Thread Limit', 'value_type' => 'number', 'default_value' => '10', 'is_active' => true]
        );
        $replyFeature = \App\Models\SystemFeature::firstOrCreate(
            ['slug' => 'forum_reply_limit'],
            ['name' => 'Forum Reply Limit', 'value_type' => 'number', 'default_value' => '50', 'is_active' => true]
        );
        
        $plan->systemFeatures()->syncWithoutDetaching([
            $threadFeature->id => ['value' => '10'],
            $replyFeature->id => ['value' => '50'],
        ]);

        $this->subscriber->subscriptions()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        // Create forum data
        $this->category = ForumCategory::factory()->create(['name' => 'General Discussion']);
        $this->otherCategory = ForumCategory::factory()->create(['name' => 'Off Topic']);
        $this->tag = ForumTag::factory()->create(['name' => 'Help']);
        $this->otherTag = ForumTag::factory()->create(['name' => 'Question']);
        
        $this->thread = ForumThread::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->subscriber->id,
            'title' => 'Test Thread',
        ]);
    }

    // ============ Public Access Tests ============

    #[Test]
    public function guest_can_view_categories(): void
    {
        $response = $this->getJson('/api/forum/categories');

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_can_view_threads_list(): void
    {
        $response = $this->getJson('/api/forum/threads');

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_can_view_thread_details(): void
    {
        $response = $this->getJson("/api/forum/threads/{$this->thread->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_can_view_posts_in_thread(): void
    {
        ForumPost::factory()->create([
            'thread_id' => $this->thread->id,
            'user_id' => $this->subscriber->id,
        ]);

        $response = $this->getJson("/api/forum/threads/{$this->thread->slug}/posts");

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_cannot_create_thread(): void
    {
        $response = $this->postJson("/api/forum/categories/{$this->category->id}/threads", [
            'title' => 'New Thread',
            'content' => 'Content here',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function guest_cannot_create_post(): void
    {
        $response = $this->postJson("/api/forum/threads/{$this->thread->id}/posts", [
            'content' => 'New post content',
        ]);

        $response->assertStatus(401);
    }

    // ============ Subscriber Access Tests ============

    #[Test]
    public function subscriber_can_create_thread(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->postJson("/api/forum/categories/{$this->category->slug}/threads", [
                'category_id' => $this->category->id,
                'title' => 'New Thread by Subscriber',
                'content' => 'This is thread content that is longer than 10 characters as required by validation.',
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function subscriber_can_create_post(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->postJson("/api/forum/threads/{$this->thread->slug}/posts", [
                'content' => 'Reply from subscriber',
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function subscriber_can_edit_own_thread(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->putJson("/api/forum/threads/{$this->thread->slug}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function subscriber_cannot_edit_others_thread(): void
    {
        $otherThread = ForumThread::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->staffUser->id,
        ]);

        $response = $this->actingAs($this->subscriber)
            ->putJson("/api/forum/threads/{$otherThread->slug}", [
                'title' => 'Trying to edit',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function subscriber_cannot_post_to_locked_thread(): void
    {
        $lockedThread = ForumThread::factory()->create([
            'category_id' => $this->category->id,
            'user_id' => $this->staffUser->id,
            'is_locked' => true,
        ]);

        $response = $this->actingAs($this->subscriber)
            ->postJson("/api/forum/threads/{$lockedThread->slug}/posts", [
                'content' => 'Trying to reply',
            ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function unsubscribed_user_cannot_create_thread(): void
    {
        // Guest has no subscription
        $response = $this->actingAs($this->guest)
            ->postJson("/api/forum/categories/{$this->category->slug}/threads", [
                'category_id' => $this->category->id,
                'title' => 'New Thread Title',
                'content' => 'This is content that is longer than 10 characters.',
            ]);

        $response->assertStatus(403);
    }

    // ============ Category/Tag Filtering Tests ============

    #[Test]
    public function threads_can_be_filtered_by_category(): void
    {
        // Create thread in other category
        ForumThread::factory()->create([
            'category_id' => $this->otherCategory->id,
            'user_id' => $this->subscriber->id,
            'title' => 'Other Thread',
        ]);

        $response = $this->getJson("/api/forum/categories/{$this->category->id}/threads");

        $response->assertStatus(200);
        // Should only include threads from the specific category
    }

    #[Test]
    public function threads_list_returns_category_data(): void
    {
        $response = $this->getJson('/api/forum/threads');

        $response->assertStatus(200);
    }

    // ============ Category/Tag Creation by Subscribers ============

    #[Test]
    public function subscriber_can_create_category(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/forum/categories', [
                'name' => 'My New Category',
                'slug' => 'my-new-category',
                'description' => 'A category I created',
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function subscriber_can_create_tag(): void
    {
        $response = $this->actingAs($this->subscriber)
            ->postJson('/api/forum/tags', [
                'name' => 'My New Tag',
                'slug' => 'my-new-tag',
            ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function subscriber_cannot_delete_category(): void
    {
        $category = ForumCategory::factory()->create();

        $response = $this->actingAs($this->subscriber)
            ->deleteJson("/api/forum/categories/{$category->slug}");

        $response->assertStatus(403);
    }

    #[Test]
    public function subscriber_cannot_delete_tag(): void
    {
        $tag = ForumTag::factory()->create();

        $response = $this->actingAs($this->subscriber)
            ->deleteJson("/api/forum/tags/{$tag->slug}");

        $response->assertStatus(403);
    }

    // ============ Staff Moderation Tests ============

    #[Test]
    public function staff_can_delete_any_thread(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->deleteJson("/api/forum/threads/{$this->thread->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function staff_can_delete_any_post(): void
    {
        $post = ForumPost::factory()->create([
            'thread_id' => $this->thread->id,
            'user_id' => $this->subscriber->id,
        ]);

        $response = $this->actingAs($this->staffUser)
            ->deleteJson("/api/forum/posts/{$post->id}");

        $response->assertStatus(200);
    }

    #[Test]
    public function staff_can_pin_thread(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->postJson("/api/forum/threads/{$this->thread->slug}/pin");

        $response->assertStatus(200);
        $this->assertTrue($this->thread->fresh()->is_pinned);
    }

    #[Test]
    public function staff_can_lock_thread(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->postJson("/api/forum/threads/{$this->thread->slug}/lock");

        $response->assertStatus(200);
        $this->assertTrue($this->thread->fresh()->is_locked);
    }

    #[Test]
    public function staff_can_delete_category(): void
    {
        $category = ForumCategory::factory()->create();

        $response = $this->actingAs($this->staffUser)
            ->deleteJson("/api/forum/categories/{$category->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function staff_can_delete_tag(): void
    {
        $tag = ForumTag::factory()->create();

        $response = $this->actingAs($this->staffUser)
            ->deleteJson("/api/forum/tags/{$tag->slug}");

        $response->assertStatus(200);
    }

    // ============ Group Tests ============

    #[Test]
    public function guest_can_view_groups(): void
    {
        $group = \App\Models\Group::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/forum/groups');

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_can_view_group_details(): void
    {
        $group = \App\Models\Group::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/forum/groups/{$group->slug}");

        $response->assertStatus(200);
    }

    #[Test]
    public function subscriber_can_join_group(): void
    {
        $group = \App\Models\Group::factory()->create(['is_active' => true, 'is_private' => false]);

        $response = $this->actingAs($this->subscriber)
            ->postJson("/api/forum/groups/{$group->slug}/join");

        $response->assertStatus(200);
    }

    #[Test]
    public function subscriber_can_leave_group(): void
    {
        $group = \App\Models\Group::factory()->create(['is_active' => true, 'is_private' => false]);
        
        // First join the group
        $group->members()->attach($this->subscriber->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($this->subscriber)
            ->postJson("/api/forum/groups/{$group->slug}/leave");

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_cannot_join_group(): void
    {
        $group = \App\Models\Group::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/forum/groups/{$group->slug}/join");

        $response->assertStatus(401);
    }

    #[Test]
    public function subscriber_cannot_join_private_group(): void
    {
        $privateGroup = \App\Models\Group::factory()->create([
            'is_active' => true,
            'is_private' => true,
        ]);

        $response = $this->actingAs($this->subscriber)
            ->postJson("/api/forum/groups/{$privateGroup->slug}/join");

        // Private groups should require invitation - expect 403
        $response->assertStatus(403);
    }

    #[Test]
    public function subscriber_can_join_open_group(): void
    {
        $openGroup = \App\Models\Group::factory()->create([
            'is_active' => true,
            'is_private' => false,
        ]);

        $response = $this->actingAs($this->subscriber)
            ->postJson("/api/forum/groups/{$openGroup->slug}/join");

        $response->assertStatus(200);
    }
}
