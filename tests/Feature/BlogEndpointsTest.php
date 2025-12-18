<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\County;
use Spatie\Permission\Models\Role;

class BlogEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $author;
    private Role $editorRole;
    private Category $category;
    private Tag $tag;
    private County $county;

    protected function setUp(): void
    {
        parent::setUp();

        // Get roles created by seeder
        $this->editorRole = Role::where('name', 'content_editor')->first();
        $adminRole = Role::where('name', 'admin')->first();

        // Create test users
        $this->user = User::factory()->create();
        $this->author = User::factory()->create();
        $this->author->assignRole($this->editorRole);

        // Create test data
        $this->county = County::factory()->create();
        $this->category = Category::factory()->create(['name' => 'Technology']);
        $this->tag = Tag::factory()->create(['name' => 'Laravel']);
    }

    /**
     * Public Blog Endpoints Tests - PublicPostController
     */

    /**
     * @test
     * List published posts without filters
     */
    public function test_index_returns_all_published_posts(): void
    {
        // Create published posts
        Post::factory(5)->published()->create([
            'user_id' => $this->author->id,
        ]);

        // Create draft posts (should not appear)
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
                    'views_count',
                    'published_at',
                ]
            ],
            'pagination' => ['total', 'per_page', 'current_page']
        ]);

        // Should only return published posts
        $this->assertEquals(5, $response->json('pagination.total'));
    }

    /**
     * @test
     * Filter posts by category
     */
    public function test_index_filters_posts_by_category(): void
    {
        $otherCategory = Category::factory()->create(['name' => 'News']);

        $postWithCategory = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $postWithCategory->categories()->attach($this->category);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ])->categories()->attach($otherCategory);

        $response = $this->getJson("/api/blog/posts?category_id={$this->category->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertContains($this->category->id,
            $response->json('data.0.categories.*.id'));
    }

    /**
     * @test
     * Filter posts by tag
     */
    public function test_index_filters_posts_by_tag(): void
    {
        $otherTag = Tag::factory()->create(['name' => 'PHP']);

        $postWithTag = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $postWithTag->tags()->attach($this->tag);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ])->tags()->attach($otherTag);

        $response = $this->getJson("/api/blog/posts?tag_id={$this->tag->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Filter posts by county
     */
    public function test_index_filters_posts_by_county(): void
    {
        $otherCounty = County::factory()->create();

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'county_id' => $this->county->id,
        ]);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'county_id' => $otherCounty->id,
        ]);

        $response = $this->getJson("/api/blog/posts?county_id={$this->county->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Search posts by title
     */
    public function test_index_searches_posts_by_title(): void
    {
        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Getting Started with Laravel',
        ]);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Vue.js Basics',
        ]);

        $response = $this->getJson('/api/blog/posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
        $this->assertStringContainsString('Laravel', $response->json('data.0.title'));
    }

    /**
     * @test
     * Sort posts by latest
     */
    public function test_index_sorts_posts_by_latest(): void
    {
        $post1 = Post::factory()->create([
            'status' => 'published',
            'user_id' => $this->author->id,
            'published_at' => now()->subDays(10),
        ]);

        $post2 = Post::factory()->create([
            'status' => 'published',
            'user_id' => $this->author->id,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/blog/posts?sort=latest');

        $response->assertStatus(200);
        $this->assertEquals($post2->id, $response->json('data.0.id'));
    }

    /**
     * @test
     * Sort posts by oldest
     */
    public function test_index_sorts_posts_by_oldest(): void
    {
        $post1 = Post::factory()->create([
            'status' => 'published',
            'user_id' => $this->author->id,
            'published_at' => now()->subDays(10),
        ]);

        $post2 = Post::factory()->create([
            'status' => 'published',
            'user_id' => $this->author->id,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/blog/posts?sort=oldest');

        $response->assertStatus(200);
        $this->assertEquals($post1->id, $response->json('data.0.id'));
    }

    /**
     * @test
     * Sort posts by views count
     */
    public function test_index_sorts_posts_by_views(): void
    {
        $post1 = Post::factory()->create([
            'status' => 'published',
            'user_id' => $this->author->id,
            'views_count' => 50,
            'published_at' => now(),
        ]);

        $post2 = Post::factory()->create([
            'status' => 'published',
            'user_id' => $this->author->id,
            'views_count' => 150,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/blog/posts?sort=views');

        $response->assertStatus(200);
        $this->assertEquals($post2->id, $response->json('data.0.id'));
    }

    /**
     * @test
     * Pagination works correctly
     */
    public function test_index_paginate_posts(): void
    {
        Post::factory(25)->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson('/api/blog/posts?per_page=10&page=1');

        $response->assertStatus(200);
        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertEquals(1, $response->json('pagination.current_page'));
    }

    /**
     * @test
     * View single published post increments view count
     */
    public function test_show_published_post_increments_views(): void
    {
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'slug' => 'test-post-slug',
            'views_count' => 10,
        ]);

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'title',
                'slug',
                'excerpt',
                'body',
                'author',
                'categories',
                'tags',
                'views_count',
                'published_at',
                'related_posts',
            ]
        ]);

        // View count should increment
        $this->assertEquals(11, $post->fresh()->views_count);
    }

    /**
     * @test
     * Cannot view draft post as unauthenticated user
     */
    public function test_show_draft_post_not_found(): void
    {
        $post = Post::factory()->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
            'slug' => 'draft-post',
        ]);

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Post not found'
        ]);
    }

    /**
     * @test
     * View post by ID also works
     */
    public function test_show_post_by_id(): void
    {
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts/{$post->id}");

        $response->assertStatus(200);
        $this->assertEquals($post->id, $response->json('data.id'));
    }

    /**
     * @test
     * Related posts are returned when viewing a post
     */
    public function test_show_post_includes_related_posts(): void
    {
        $post = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $post->categories()->attach($this->category);

        // Create related post with same category
        $relatedPost = Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);
        $relatedPost->categories()->attach($this->category);

        // Create unrelated post
        Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->getJson("/api/blog/posts/{$post->id}");

        $response->assertStatus(200);
        $relatedPosts = $response->json('data.related_posts');
        $this->assertGreaterThan(0, count($relatedPosts));
        $this->assertContains($relatedPost->id, collect($relatedPosts)->pluck('id')->toArray());
    }

    /**
     * @test
     * User can view their own posts (draft and published)
     */
    public function test_my_posts_returns_user_posts(): void
    {
        $draftPost = Post::factory()->create([
            'status' => 'draft',
            'user_id' => $this->user->id,
        ]);

        $publishedPost = Post::factory()->published()->create([
            'user_id' => $this->user->id,
        ]);

        // Other user's post should not appear
        Post::factory()->published()->create([
            'user_id' => $this->author->id,
        ]);

        $this->user->assignRole($this->editorRole);

        $response = $this->actingAs($this->user)->getJson('/api/blog/my-posts');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }

    /**
     * @test
     * my_posts requires authentication
     */
    public function test_my_posts_requires_authentication(): void
    {
        $response = $this->getJson('/api/blog/my-posts');

        $response->assertStatus(401);
    }

    /**
     * @test
     * Filter my posts by status
     */
    public function test_my_posts_filter_by_status(): void
    {
        $this->user->assignRole($this->editorRole);

        Post::factory(2)->create([
            'status' => 'draft',
            'user_id' => $this->user->id,
        ]);

        Post::factory(3)->published()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/blog/my-posts?status=draft');

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }

    /**
     * @test
     * Search own posts
     */
    public function test_my_posts_search(): void
    {
        $this->user->assignRole($this->editorRole);

        Post::factory()->create([
            'status' => 'draft',
            'user_id' => $this->user->id,
            'title' => 'Laravel Tutorial',
        ]);

        Post::factory()->create([
            'status' => 'draft',
            'user_id' => $this->user->id,
            'title' => 'Vue Guide',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/blog/my-posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * Admin Blog Endpoints Tests - PostAdminController
     */

    /**
     * @test
     * Admin can view create form metadata
     */
    public function test_create_returns_form_metadata(): void
    {
        $this->user->assignRole('admin');

        $response = $this->actingAs($this->user)->getJson('/api/admin/blog/posts/create');

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
     * Non-admin cannot access create form
     */
    public function test_create_requires_admin(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/admin/blog/posts/create');

        $response->assertStatus(403);
    }

    /**
     * @test
     * Admin can list all posts (admin view)
     */
    public function test_index_admin_lists_all_posts(): void
    {
        $this->user->assignRole('admin');
        $this->user = $this->user->fresh();

        Post::factory(3)->published()->create([
            'user_id' => $this->author->id,
        ]);

        Post::factory(2)->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/admin/blog/posts');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('pagination.total'));
    }

    /**
     * @test
     * Admin can filter posts by status
     */
    public function test_index_admin_filter_by_status(): void
    {
        $this->user->assignRole('admin');
        $this->user = $this->user->fresh();

        Post::factory(3)->published()->create([
            'user_id' => $this->author->id,
        ]);

        Post::factory(2)->create([
            'status' => 'draft',
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/blog/posts?status=published');

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('pagination.total'));
    }

    /**
     * @test
     * Admin can filter posts by author
     */
    public function test_index_admin_filter_by_author(): void
    {
        $this->user->assignRole('admin');
        $this->user = $this->user->fresh();

        Post::factory(2)->published()->create([
            'user_id' => $this->author->id,
        ]);

        $anotherAuthor = User::factory()->create();
        Post::factory(3)->published()->create([
            'user_id' => $anotherAuthor->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/admin/blog/posts?author_id={$this->author->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }

    /**
     * @test
     * Admin can search posts in admin view
     */
    public function test_index_admin_search(): void
    {
        $this->user->assignRole('admin');
        $this->user = $this->user->fresh();

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Laravel Advanced Topics',
        ]);

        Post::factory()->published()->create([
            'user_id' => $this->author->id,
            'title' => 'Vue Basics',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/admin/blog/posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Admin can create a post
     */
    public function test_store_creates_new_post(): void
    {
        $this->user->assignRole('admin');

        $payload = [
            'title' => 'New Blog Post',
            'excerpt' => 'This is an excerpt',
            'content' => 'This is the full content of the blog post',
            'status' => 'draft',
            'county_id' => $this->county->id,
            'category_ids' => [$this->category->id],
            'tag_ids' => [$this->tag->id],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/blog/posts', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'title' => 'New Blog Post',
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * @test
     * Cannot create post with invalid data
     */
    public function test_store_validates_required_fields(): void
    {
        $this->user->assignRole('admin');

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/blog/posts', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'excerpt', 'content', 'county_id', 'category_ids']);
    }

    /**
     * @test
     * Admin can update a post
     */
    public function test_update_edits_existing_post(): void
    {
        $this->user->assignRole('admin');
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $payload = [
            'title' => 'Updated Title',
            'excerpt' => 'Updated excerpt',
            'content' => 'Updated content',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/blog/posts/{$post->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * @test
     * Admin can publish a post
     */
    public function test_publish_post(): void
    {
        $this->user->assignRole('admin');
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/blog/posts/{$post->id}", ['status' => 'published']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'published',
        ]);
    }

    /**
     * @test
     * Admin can delete a post
     */
    public function test_destroy_deletes_post(): void
    {
        $this->user->assignRole('admin');
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/admin/blog/posts/{$post->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($post);
    }

    /**
     * @test
     * Editor can update only their own posts
     */
    public function test_editor_can_only_update_own_posts(): void
    {
        $this->user->assignRole('editor');
        $otherPost = Post::factory()->create([
            'user_id' => $this->author->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/admin/blog/posts/{$otherPost->id}", [
                'title' => 'Hacked Title'
            ]);

        $response->assertStatus(403);
    }
}
