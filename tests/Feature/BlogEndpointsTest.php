<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\County;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemFeature;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class BlogEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $author;
    private User $staffUser;
    private User $subscribedUser;
    private Role $editorRole;
    private Role $adminRole;
    private Category $category;
    private Tag $tag;
    private County $county;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->user = User::factory()->create();
        $this->author = User::factory()->create();
        $this->staffUser = User::factory()->create(['email' => 'staff@example.com']);
        $this->subscribedUser = User::factory()->create(['email' => 'subscriber@example.com']);

        // Create roles with 'api' guard
        $this->editorRole = Role::where('name', 'content_editor')->where('guard_name', 'api')->first()
            ?? Role::create(['name' => 'content_editor', 'guard_name' => 'api']);
        
        $this->adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first()
            ?? Role::create(['name' => 'admin', 'guard_name' => 'api']);

        // Create permissions with 'api' guard
        $permissions = ['create_posts', 'edit_posts', 'edit_any_post', 'delete_posts', 'publish_posts'];
        foreach ($permissions as $permission) {
            $perm = Permission::where('name', $permission)->where('guard_name', 'api')->first();
            if (!$perm) {
                Permission::create(['name' => $permission, 'guard_name' => 'api']);
            }
        }

        // Sync permissions to roles
        $this->editorRole->syncPermissions($permissions);
        $this->adminRole->syncPermissions($permissions);

        // Assign roles to staff user (making them isStaffMember())
        $this->staffUser->assignRole($this->adminRole);
        
        // Assign roles to author (making them isStaffMember())
        $this->author->assignRole($this->editorRole);

        // Create a plan with blog creation limit
        $this->plan = Plan::factory()->create([
            'name' => 'Blogger Plan',
        ]);

        // Create blog_creation_limit system feature
        $blogFeature = SystemFeature::firstOrCreate(
            ['slug' => 'blog_creation_limit'],
            [
                'name' => 'Blog Creation Limit',
                'description' => 'Maximum number of blog posts per month',
                'value_type' => 'integer',
                'default_value' => 0,
            ]
        );

        // Attach feature to plan with value 3
        $this->plan->systemFeatures()->attach($blogFeature, ['value' => 3]);

        // Create active subscription for subscribed user
        $this->subscribedUser->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Create test data
        $this->county = County::factory()->create();
        $this->category = Category::factory()->create(['name' => 'Technology', 'created_by' => $this->staffUser->id]);
        $this->tag = Tag::factory()->create(['name' => 'Laravel', 'created_by' => $this->staffUser->id]);
    }

    /**
     * PUBLIC BLOG ENDPOINTS TESTS - PublicPostController
     */

    /**
     * @test
     * List published posts without filters
     */
    public function test_index_returns_all_published_posts(): void
    {
        Post::factory(5)->published()->create([
            'user_id' => $this->author->id,
        ]);

        Post::factory(2)->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson('/api/blog/posts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'excerpt',
                    'author',
                ]
            ],
            'pagination' => ['total', 'per_page', 'current_page']
        ]);
    }

    /**
     * @test
     * Filter posts by category
     */
    public function test_index_filters_posts_by_category(): void
    {
        $category = Category::factory()->create(['name' => 'Featured']);
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $post->categories()->attach($category);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts?category={$category->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Filter posts by tag
     */
    public function test_index_filters_posts_by_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'Featured']);
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $post->tags()->attach($tag);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts?tag={$tag->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Filter posts by county
     */
    public function test_index_filters_posts_by_county(): void
    {
        $county = County::factory()->create();
        Post::factory(2)->published()->create([
            'user_id' => $this->author->id,
            'county_id' => $county->id,
        ]);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts?county_id={$county->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }

    /**
     * @test
     * Search posts by title
     */
    public function test_index_searches_posts_by_title(): void
    {
        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Laravel Tips and Tricks',
        ]);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Vue.js Guide',
        ]);

        $response = $this->getJson('/api/blog/posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Sort posts by latest
     */
    public function test_index_sorts_posts_by_latest(): void
    {
        $post1 = Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'First Post',
        ]);
        sleep(1);
        $post2 = Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Second Post',
        ]);

        $response = $this->getJson('/api/blog/posts?sort=latest');

        $response->assertStatus(200);
        $this->assertEquals('Second Post', $response->json('data.0.title'));
    }

    /**
     * @test
     * Paginate posts
     */
    public function test_index_paginate_posts(): void
    {
        Post::factory(25)->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson('/api/blog/posts?per_page=10&page=1');

        $response->assertStatus(200);
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertEquals(10, $response->json('pagination.per_page'));
    }

    /**
     * @test
     * Show published post increments views
     */
    public function test_show_published_post_increments_views(): void
    {
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $this->getJson("/api/blog/posts/{$post->slug}");

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(200);
        $this->assertGreater($response->json('data.views_count'), 0);
    }

    /**
     * @test
     * Cannot show draft post without auth
     */
    public function test_show_draft_post_not_found(): void
    {
        $post = Post::factory()->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(404);
    }

    /**
     * @test
     * Show post by ID
     */
    public function test_show_post_by_id(): void
    {
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'title',
                'slug',
                'author',
                'categories',
                'tags',
            ]
        ]);
    }

    /**
     * @test
     * Show post includes related posts
     */
    public function test_show_post_includes_related_posts(): void
    {
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $post->categories()->attach($this->category);

        Post::factory(3)->published()->create([
            'user_id' => $this->author->id,
        ])->each(function($p) {
            $p->categories()->attach($this->category);
        });

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(200);
        $this->assertGreater(count($response->json('data.related_posts')), 0);
    }

    /**
     * AUTHENTICATED USER ENDPOINTS - Author/User Actions
     */

    /**
     * @test
     * My posts returns user's posts
     */
    public function test_my_posts_returns_user_posts(): void
    {
        Post::factory(3)->create([
            'user_id' => $this->subscribedUser->id,
        ]);

        Post::factory(2)->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->getJson('/api/author/posts');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('pagination.total'));
    }

    /**
     * @test
     * My posts requires authentication
     */
    public function test_my_posts_requires_authentication(): void
    {
        $response = $this->getJson('/api/author/posts');

        $response->assertStatus(401);
    }

    /**
     * @test
     * Filter my posts by status
     */
    public function test_my_posts_filter_by_status(): void
    {
        Post::factory(2)->create([
            'user_id' => $this->subscribedUser->id,
            'status' => 'published',
        ]);

        Post::factory(1)->create([
            'user_id' => $this->subscribedUser->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->getJson('/api/author/posts?status=draft');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Search my posts
     */
    public function test_my_posts_search(): void
    {
        Post::factory()->create([
            'user_id' => $this->subscribedUser->id,
            'title' => 'Laravel Tips',
        ]);

        Post::factory()->create([
            'user_id' => $this->subscribedUser->id,
            'title' => 'Vue Basics',
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->getJson('/api/author/posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * STAFF/ADMIN ENDPOINTS - AdminPostController
     */

    /**
     * @test
     * Create form returns metadata
     */
    public function test_create_returns_form_metadata(): void
    {
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/admin/blog/posts/create');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'categories',
                'tags'
            ]
        ]);
    }

    /**
     * @test
     * Non-staff cannot access create form
     */
    public function test_create_requires_staff(): void
    {
        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->getJson('/api/admin/blog/posts/create');

        $response->assertStatus(403);
    }

    /**
     * @test
     * Staff can list all posts (admin view)
     */
    public function test_index_admin_lists_all_posts(): void
    {
        Post::factory(3)->published()->create([
            'user_id' => $this->author->id,
        ]);

        Post::factory(2)->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/admin/blog/posts');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('pagination.total'));
    }

    /**
     * @test
     * Staff can filter posts by status
     */
    public function test_index_admin_filter_by_status(): void
    {
        Post::factory(3)->published()->create([
            'user_id' => $this->author->id,
        ]);

        Post::factory(2)->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/admin/blog/posts?status=published');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('pagination.total'));
    }

    /**
     * @test
     * Staff can filter posts by author
     */
    public function test_index_admin_filter_by_author(): void
    {
        Post::factory(2)->published()->create([
            'user_id' => $this->author->id,
        ]);

        $anotherAuthor = User::factory()->create();
        Post::factory(3)->published()->create([
            'user_id' => $anotherAuthor->id,
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/admin/blog/posts?author_id={$this->author->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }

    /**
     * @test
     * Staff can search posts in admin view
     */
    public function test_index_admin_search(): void
    {
        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Laravel Advanced Topics',
        ]);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Vue Basics',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson('/api/admin/blog/posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * STAFF POST CREATION - Role-based (unlimited quota)
     */

    /**
     * @test
     * Staff can create posts without quota restrictions
     */
    public function test_staff_can_create_posts_unlimited(): void
    {
        // Create 10 posts for staff user (should succeed even beyond quota)
        for ($i = 0; $i < 10; $i++) {
            $payload = [
                'title' => "Staff Post $i",
                'excerpt' => 'Staff excerpt',
                'content' => 'Staff content',
                'status' => 'draft',
                'county_id' => $this->county->id,
                'category_ids' => [$this->category->id],
            ];

            $response = $this->actingAs($this->staffUser, 'sanctum')
                ->postJson('/api/admin/blog/posts', $payload);

            $this->assertEquals(201, $response->status(), "Failed at iteration $i");
        }

        // Verify all posts created
        $this->assertEquals(10, Post::where('user_id', $this->staffUser->id)->count());
    }

    /**
     * @test
     * Staff can update any post
     */
    public function test_staff_can_update_any_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'Original Title',
        ]);

        $payload = [
            'title' => 'Updated by Staff',
            'excerpt' => 'Updated excerpt',
            'content' => 'Updated content',
        ];

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->putJson("/api/admin/blog/posts/{$post->slug}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated by Staff',
        ]);
    }

    /**
     * @test
     * Staff can delete any post
     */
    public function test_staff_can_delete_any_post(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->deleteJson("/api/admin/blog/posts/{$post->slug}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($post);
    }

    /**
     * SUBSCRIPTION-BASED POST CREATION - Feature-gating with Quota
     */

    /**
     * @test
     * Subscribed user can create posts up to quota limit
     */
    public function test_subscriber_can_create_posts_within_quota(): void
    {
        // Create 3 posts (at limit)
        for ($i = 1; $i <= 3; $i++) {
            $payload = [
                'title' => "Subscriber Post $i",
                'excerpt' => 'Subscriber excerpt',
                'content' => 'Subscriber content',
                'status' => 'draft',
                'county_id' => $this->county->id,
                'category_ids' => [$this->category->id],
            ];

            $response = $this->actingAs($this->subscribedUser, 'sanctum')
                ->postJson('/api/author/posts', $payload);

            $this->assertEquals(201, $response->status(), "Failed at post $i");
        }

        // Verify exactly 3 posts created
        $this->assertEquals(3, Post::where('user_id', $this->subscribedUser->id)->count());
    }

    /**
     * @test
     * Subscribed user cannot exceed monthly quota
     */
    public function test_subscriber_cannot_exceed_quota(): void
    {
        // Create 3 posts (at limit)
        for ($i = 1; $i <= 3; $i++) {
            Post::factory()->create([
                'user_id' => $this->subscribedUser->id,
                'created_at' => now(),
            ]);
        }

        // Try to create 4th post (should fail)
        $payload = [
            'title' => 'Fourth Post (Should Fail)',
            'excerpt' => 'Subscriber excerpt',
            'content' => 'Subscriber content',
            'status' => 'draft',
            'county_id' => $this->county->id,
            'category_ids' => [$this->category->id],
        ];

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->postJson('/api/author/posts', $payload);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'message' // Error message about quota exceeded
        ]);
    }

    /**
     * @test
     * User without subscription cannot create posts
     */
    public function test_unsubscribed_user_cannot_create_posts(): void
    {
        $unsubscribedUser = User::factory()->create(['email' => 'unsubscribed@example.com']);

        $payload = [
            'title' => 'Unsubscribed Post',
            'excerpt' => 'Excerpt',
            'content' => 'Content',
            'status' => 'draft',
            'county_id' => $this->county->id,
            'category_ids' => [$this->category->id],
        ];

        $response = $this->actingAs($unsubscribedUser, 'sanctum')
            ->postJson('/api/author/posts', $payload);

        $response->assertStatus(422);
    }

    /**
     * @test
     * Subscribed user can only edit their own posts
     */
    public function test_subscriber_can_only_edit_own_posts(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->putJson("/api/author/posts/{$post->slug}", [
                'title' => 'Hacked Title'
            ]);

        $response->assertStatus(403);
    }

    /**
     * @test
     * Subscribed user can edit their own posts
     */
    public function test_subscriber_can_edit_own_posts(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->subscribedUser->id,
            'title' => 'Original',
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->putJson("/api/author/posts/{$post->slug}", [
                'title' => 'Updated by Owner'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated by Owner',
        ]);
    }

    /**
     * @test
     * Subscribed user can delete their own posts
     */
    public function test_subscriber_can_delete_own_posts(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->subscribedUser->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->deleteJson("/api/author/posts/{$post->slug}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($post);
    }

    /**
     * @test
     * Subscribed user cannot delete others' posts
     */
    public function test_subscriber_cannot_delete_others_posts(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->deleteJson("/api/author/posts/{$post->slug}");

        $response->assertStatus(403);
    }

    /**
     * TAG & CATEGORY OWNERSHIP TESTS
     */

    /**
     * @test
     * User can create tags
     */
    public function test_user_can_create_tags(): void
    {
        $payload = [
            'name' => 'New Tag',
            'slug' => 'new-tag',
        ];

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->postJson('/api/tags', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tags', [
            'name' => 'New Tag',
            'created_by' => $this->subscribedUser->id,
        ]);
    }

    /**
     * @test
     * User can update only their own tags
     */
    public function test_user_can_update_own_tags(): void
    {
        $tag = Tag::factory()->create([
            'name' => 'Original Tag',
            'created_by' => $this->subscribedUser->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->putJson("/api/tags/{$tag->id}", [
                'name' => 'Updated Tag',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
        ]);
    }

    /**
     * @test
     * User cannot delete tags (only staff)
     */
    public function test_user_cannot_delete_tags(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->subscribedUser->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(403);
    }

    /**
     * @test
     * Staff can delete tags with confirmation
     */
    public function test_staff_can_delete_tags_with_affected_posts(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->subscribedUser->id,
        ]);

        // Create posts with this tag
        $post1 = Post::factory()->create(['user_id' => $this->author->id]);
        $post2 = Post::factory()->create(['user_id' => $this->author->id]);
        $post1->tags()->attach($tag);
        $post2->tags()->attach($tag);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($tag);
    }

    /**
     * @test
     * User can create categories
     */
    public function test_user_can_create_categories(): void
    {
        $payload = [
            'name' => 'New Category',
            'slug' => 'new-category',
        ];

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->postJson('/api/categories', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'created_by' => $this->subscribedUser->id,
        ]);
    }

    /**
     * @test
     * User cannot delete categories (only staff)
     */
    public function test_user_cannot_delete_categories(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->subscribedUser->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }

    /**
     * @test
     * Staff can delete categories with affected posts info
     */
    public function test_staff_can_delete_categories_with_affected_posts(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->subscribedUser->id,
        ]);

        // Create posts with this category
        $post1 = Post::factory()->create(['user_id' => $this->author->id]);
        $post2 = Post::factory()->create(['user_id' => $this->author->id]);
        $post1->categories()->attach($category);
        $post2->categories()->attach($category);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($category);
    }

    /**
     * VALIDATION TESTS
     */

    /**
     * @test
     * Cannot create post with invalid data
     */
    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->postJson('/api/author/posts', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'excerpt', 'content', 'county_id', 'category_ids']);
    }

    /**
     * @test
     * Slug must be unique
     */
    public function test_slug_must_be_unique(): void
    {
        Post::factory()->create([
            'user_id' => $this->author->id,
            'slug' => 'existing-slug',
        ]);

        $payload = [
            'title' => 'New Post',
            'excerpt' => 'Excerpt',
            'content' => 'Content',
            'slug' => 'existing-slug',
            'status' => 'draft',
            'county_id' => $this->county->id,
            'category_ids' => [$this->category->id],
        ];

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson('/api/admin/blog/posts', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slug']);
    }
}




