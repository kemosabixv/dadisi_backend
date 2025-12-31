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
use PHPUnit\Framework\Attributes\Test;

class BlogEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $shouldSeedRoles = true;

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

        // 1. Create test users first
        $this->user = User::factory()->create();
        $this->author = User::factory()->create();
        $this->staffUser = User::factory()->create(['email' => 'staff@example.com']);
        $this->subscribedUser = User::factory()->create(['email' => 'subscriber@example.com']);

        // 2. Create foundational data
        $this->county = County::factory()->create();
        $this->category = Category::factory()->create(['name' => 'Technology', 'created_by' => $this->staffUser->id]);
        $this->tag = Tag::factory()->create(['name' => 'Laravel', 'created_by' => $this->staffUser->id]);

        // 3. Create roles with 'api' guard
        $this->editorRole = Role::where('name', 'content_editor')->where('guard_name', 'api')->first()
            ?? Role::create(['name' => 'content_editor', 'guard_name' => 'api']);
        
        $this->adminRole = Role::where('name', 'admin')->where('guard_name', 'api')->first()
            ?? Role::create(['name' => 'admin', 'guard_name' => 'api']);

        // 4. Create permissions with 'api' guard
        $permissions = [
            'create_posts', 'edit_posts', 'edit_any_post', 'delete_posts', 
            'publish_posts', 'view_posts', 'view_all_posts', 'restore_posts', 
            'force_delete_posts'
        ];
        foreach ($permissions as $permission) {
            $perm = Permission::where('name', $permission)->where('guard_name', 'api')->first();
            if (!$perm) {
                Permission::create(['name' => $permission, 'guard_name' => 'api']);
            }
        }

        // 5. Sync permissions to roles
        $this->editorRole->syncPermissions($permissions);
        $this->adminRole->syncPermissions($permissions);

        // 6. Create profiles and assign flags (needs county)
        $this->author->memberProfile()->create([
            'first_name' => 'Author',
            'last_name' => 'Test',
            'county_id' => $this->county->id,
            'is_staff' => false
        ]);

        $this->subscribedUser->memberProfile()->create([
            'first_name' => 'Subscriber',
            'last_name' => 'Test',
            'county_id' => $this->county->id,
            'is_staff' => false
        ]);

        $this->staffUser->memberProfile()->create([
            'first_name' => 'Staff',
            'last_name' => 'Test',
            'county_id' => $this->county->id,
            'is_staff' => true
        ]);

        // 7. Assign roles
        $this->staffUser->assignRole($this->adminRole);
        $this->author->assignRole($this->editorRole);

        // 8. Create a plan with blog creation limit
        $this->plan = Plan::factory()->create([
            'name' => 'Blogger Plan',
        ]);

        // 9. Create blog_creation_limit system feature
        $blogFeature = SystemFeature::firstOrCreate(
            ['slug' => 'blog_creation_limit'],
            [
                'name' => 'Blog Creation Limit',
                'description' => 'Maximum number of blog posts per month',
                'value_type' => 'integer',
                'default_value' => 0,
            ]
        );

        // 10. Attach feature and subscription
        $this->plan->systemFeatures()->attach($blogFeature, ['value' => 3]);

        $this->subscribedUser->subscriptions()->create([
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * PUBLIC BLOG ENDPOINTS TESTS - PublicPostController
     */

    #[Test]
    public function index_returns_all_published_posts(): void
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

    #[Test]
    public function index_filters_posts_by_category(): void
    {
        $category = Category::factory()->create(['name' => 'Featured']);
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $post->categories()->attach($category);

        // Create a post in a different category
        $otherCategory = Category::factory()->create(['name' => 'Other']);
        $otherPost = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $otherPost->categories()->attach($otherCategory);

        $response = $this->getJson("/api/blog/posts?category={$category->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    #[Test]
    public function index_filters_posts_by_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'Featured']);
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $post->tags()->attach($tag);

        // Create a post with a different tag
        $otherTag = Tag::factory()->create(['name' => 'Other']);
        $otherPost = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $otherPost->tags()->attach($otherTag);

        $response = $this->getJson("/api/blog/posts?tag={$tag->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    #[Test]
    public function index_filters_posts_by_county(): void
    {
        $county = County::factory()->create();
        Post::factory(2)->published()->create([
            'user_id' => $this->author->id,
            'county_id' => $county->id,
        ]);

        $otherCounty = County::factory()->create();
        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'county_id' => $otherCounty->id,
        ]);

        $response = $this->getJson("/api/blog/posts?county_id={$county->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }

    #[Test]
    public function index_searches_posts_by_title(): void
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

    #[Test]
    public function index_sorts_posts_by_latest(): void
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

    #[Test]
    public function index_paginate_posts(): void
    {
        Post::factory(25)->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson('/api/blog/posts?per_page=10&page=1');

        $response->assertStatus(200);
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertEquals(10, $response->json('pagination.per_page'));
    }

    #[Test]
    public function show_published_post_increments_views(): void
    {
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $this->getJson("/api/blog/posts/{$post->slug}");

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(200);
        $this->assertGreaterThan(0, $response->json('data.views_count'));
    }

    #[Test]
    public function show_draft_post_not_found(): void
    {
        $post = Post::factory()->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(404);
    }

    #[Test]
    public function show_post_by_id(): void
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

    #[Test]
    public function show_post_includes_related_posts(): void
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
        $this->assertGreaterThan(0, count($response->json('data.related_posts')));
    }

    /**
     * AUTHENTICATED USER ENDPOINTS - Author/User Actions
     */

    #[Test]
    public function my_posts_returns_user_posts(): void
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

    #[Test]
    public function my_posts_requires_authentication(): void
    {
        $response = $this->getJson('/api/author/posts');

        $response->assertStatus(401);
    }

    #[Test]
    public function my_posts_filter_by_status(): void
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

    #[Test]
    public function my_posts_search(): void
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

    #[Test]
    public function create_returns_form_metadata(): void
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

    #[Test]
    public function create_requires_staff(): void
    {
        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->getJson('/api/admin/blog/posts/create');

        $response->assertStatus(403);
    }

    #[Test]
    public function index_admin_lists_all_posts(): void
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

    #[Test]
    public function index_admin_filter_by_status(): void
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

    #[Test]
    public function index_admin_filter_by_author(): void
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

    #[Test]
    public function index_admin_search(): void
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

    #[Test]
    public function staff_can_create_posts_unlimited(): void
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

    #[Test]
    public function staff_can_update_any_post(): void
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

    #[Test]
    public function staff_can_delete_any_post(): void
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

    #[Test]
    public function subscriber_can_create_posts_within_quota(): void
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

    #[Test]
    public function subscriber_cannot_exceed_quota(): void
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

    #[Test]
    public function unsubscribed_user_cannot_create_posts(): void
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

    #[Test]
    public function subscriber_can_only_edit_own_posts(): void
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

    #[Test]
    public function subscriber_can_edit_own_posts(): void
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

    #[Test]
    public function subscriber_can_delete_own_posts(): void
    {
        $post = Post::factory()->create([
            'user_id' => $this->subscribedUser->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->deleteJson("/api/author/posts/{$post->slug}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($post);
    }

    #[Test]
    public function subscriber_cannot_delete_others_posts(): void
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

    #[Test]
    public function user_can_create_tags(): void
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

    #[Test]
    public function user_can_update_own_tags(): void
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

    #[Test]
    public function user_cannot_delete_tags(): void
    {
        $tag = Tag::factory()->create([
            'created_by' => $this->subscribedUser->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->deleteJson("/api/tags/{$tag->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function staff_can_delete_tags_with_affected_posts(): void
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

    #[Test]
    public function user_can_create_categories(): void
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

    #[Test]
    public function user_cannot_delete_categories(): void
    {
        $category = Category::factory()->create([
            'created_by' => $this->subscribedUser->id,
        ]);

        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function staff_can_delete_categories_with_affected_posts(): void
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

    #[Test]
    public function store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->subscribedUser, 'sanctum')
            ->postJson('/api/author/posts', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'content']);
    }

    #[Test]
    public function slug_must_be_unique(): void
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





