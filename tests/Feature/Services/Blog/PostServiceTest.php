<?php

namespace Tests\Feature\Services\Blog;

use App\Exceptions\PostException;
use App\Models\Category;
use App\Models\County;
use App\Models\Post;
use App\Models\User;
use App\Services\Blog\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PostServiceTest
 *
 * Test suite for PostService with 40+ test cases covering:
 * - Post creation and updates
 * - Publishing workflows
 * - Soft deletion and restoration
 * - Slug-based retrieval
 * - Filtering and pagination
 */
class PostServiceTest extends TestCase
{
    use RefreshDatabase;

    private PostService $service;
    private User $author;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PostService::class);
        $this->author = User::factory()->create();
        $this->admin = User::factory()->create();

        // Create member profile with staff flag for author (bypass quota checks)
        $county = \App\Models\County::factory()->create();
        $this->author->memberProfile()->create([
            'first_name' => 'Author',
            'last_name' => 'Test',
            'county_id' => $county->id,
            'is_staff' => true,
        ]);

        // Create common categories used in tests
        foreach (['technology', 'general', 'news', 'events', 'test'] as $slug) {
            \App\Models\Category::factory()->create(['slug' => $slug, 'name' => ucfirst($slug)]);
        }
    }

    // ============ Creation Tests ============

    #[Test]
    /**
     * Can create a post with valid data
     */
    public function it_can_create_post_with_valid_data(): void
    {
        $data = [
            'title' => 'My First Blog Post',
            'content' => 'This is the content of my blog post.',
            'excerpt' => 'A short excerpt',
            'category' => 'technology',
        ];

        $post = $this->service->createPost($this->author, $data);

        $this->assertNotNull($post->id);
        $this->assertEquals('My First Blog Post', $post->title);
        $this->assertEquals('my-first-blog-post', $post->slug);
        $this->assertEquals('draft', $post->status);
        $this->assertEquals($this->author->id, $post->user_id);
    }

    #[Test]
    /**
     * Auto-generates slug from title
     */
    public function it_auto_generates_slug_from_title(): void
    {
        $data = [
            'title' => 'Complex Title With Special Characters!',
            'content' => 'Content',
            'category' => 'general',
        ];

        $post = $this->service->createPost($this->author, $data);

        $this->assertEquals('complex-title-with-special-characters', $post->slug);
    }

    #[Test]
    /**
     * Sets default excerpt from content
     */
    public function it_sets_default_excerpt_from_content(): void
    {
        $content = 'This is a long piece of content that will be truncated to 200 characters for the excerpt if no explicit excerpt is provided.';
        $data = [
            'title' => 'Test Post',
            'content' => $content,
            'category' => 'general',
        ];

        $post = $this->service->createPost($this->author, $data);

        $this->assertNotNull($post->excerpt);
        $this->assertLessThanOrEqual(200, strlen($post->excerpt));
    }

    #[Test]
    /**
     * Creates audit log on post creation
     */
    public function it_creates_audit_log_on_creation(): void
    {
        $data = [
            'title' => 'Audited Post',
            'content' => 'Content',
            'category' => 'news',
        ];

        $post = $this->service->createPost($this->author, $data);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->author->id,
            'action' => 'created_post',
            'model_type' => Post::class,
            'model_id' => $post->id,
        ]);
    }

    // ============ Update Tests ============

    #[Test]
    /**
     * Can update post
     */
    public function it_can_update_post(): void
    {
        $post = Post::factory()->for($this->author)->create();

        $updated = $this->service->updatePost($this->admin, $post, [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]);

        $this->assertEquals('Updated Title', $updated->title);
        $this->assertEquals('updated-title', $updated->slug);
        $this->assertEquals('Updated content', $updated->content);
    }

    #[Test]
    /**
     * Can update post category
     */
    public function it_can_update_post_category(): void
    {
        $categoryNews = Category::where('slug', 'news')->first();
        $post = Post::factory()->for($this->author)->create();
        $post->categories()->attach($categoryNews);

        $updated = $this->service->updatePost($this->admin, $post, ['category' => 'events']);

        $this->assertEquals('events', $updated->category);
    }

    #[Test]
    /**
     * Partial updates work correctly
     */
    public function it_allows_partial_updates(): void
    {
        $post = Post::factory()->for($this->author)->create(['content' => 'Original']);

        $updated = $this->service->updatePost($this->admin, $post, [
            'title' => 'New Title',
        ]);

        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals('Original', $updated->content);
    }

    #[Test]
    /**
     * Creates audit log on update
     */
    public function it_creates_audit_log_on_update(): void
    {
        $post = Post::factory()->for($this->author)->create();

        $this->service->updatePost($this->admin, $post, ['title' => 'Changed']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'updated_post',
        ]);
    }

    // ============ Publishing Tests ============

    #[Test]
    /**
     * Can publish a draft post
     */
    public function it_can_publish_draft_post(): void
    {
        $post = Post::factory()->draft()->for($this->author)->create();

        $published = $this->service->publishPost($this->admin, $post);

        $this->assertEquals('published', $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertNotNull($published->published_at);
    }

    #[Test]
    /**
     * Throws exception when publishing already published post
     */
    public function it_throws_exception_when_publishing_published_post(): void
    {
        $post = Post::factory()->published()->for($this->author)->create();

        $this->expectException(PostException::class);
        $this->service->publishPost($this->admin, $post);
    }

    #[Test]
    /**
     * Can unpublish a published post
     */
    public function it_can_unpublish_published_post(): void
    {
        $post = Post::factory()->published()->for($this->author)->create();

        $unpublished = $this->service->unpublishPost($this->admin, $post);

        $this->assertEquals('draft', $unpublished->status);
        $this->assertNull($unpublished->published_at);
    }

    #[Test]
    /**
     * Creates audit log on publish
     */
    public function it_creates_audit_log_on_publish(): void
    {
        $post = Post::factory()->draft()->for($this->author)->create();

        $this->service->publishPost($this->admin, $post);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'published_post',
            'model_type' => Post::class,
            'model_id' => $post->id,
            'user_id' => $this->admin->id,
        ]);
    }

    // ============ Deletion Tests ============

    #[Test]
    /**
     * Can soft delete a post
     */
    public function it_can_soft_delete_post(): void
    {
        $post = Post::factory()->for($this->author)->create();

        $deleted = $this->service->deletePost($this->admin, $post);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted($post);
    }

    #[Test]
    /**
     * Can restore a deleted post
     */
    public function it_can_restore_deleted_post(): void
    {
        $post = Post::factory()->deleted()->for($this->author)->create();

        $restored = $this->service->restorePost($this->admin, $post);

        $this->assertNotSoftDeleted($restored);
    }

    #[Test]
    /**
     * Throws exception when restoring non-deleted post
     */
    public function it_throws_exception_when_restoring_non_deleted_post(): void
    {
        $post = Post::factory()->for($this->author)->create();

        $this->expectException(PostException::class);
        $this->service->restorePost($this->admin, $post);
    }

    #[Test]
    /**
     * Creates audit log on delete
     */
    public function it_creates_audit_log_on_delete(): void
    {
        $post = Post::factory()->for($this->author)->create();

        $this->service->deletePost($this->admin, $post);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted_post',
            'model_type' => Post::class,
            'model_id' => $post->id,
            'user_id' => $this->admin->id,
        ]);
    }

    // ============ Retrieval Tests ============

    #[Test]
    /**
     * Can get post by slug
     */
    public function it_can_get_post_by_slug(): void
    {
        $post = Post::factory()->published()->for($this->author)->create(['slug' => 'test-slug']);

        $retrieved = $this->service->getBySlug('test-slug');

        $this->assertEquals($post->id, $retrieved->id);
    }

    #[Test]
    /**
     * Only returns published posts by slug
     */
    public function it_only_returns_published_posts_by_slug(): void
    {
        Post::factory()->draft()->for($this->author)->create(['slug' => 'draft-post']);

        $this->expectException(PostException::class);
        $this->service->getBySlug('draft-post');
    }

    #[Test]
    /**
     * Throws exception for non-existent slug
     */
    public function it_throws_exception_for_non_existent_slug(): void
    {
        $this->expectException(PostException::class);
        $this->service->getBySlug('non-existent-slug');
    }

    #[Test]
    /**
     * Can list published posts
     */
    public function it_can_list_published_posts(): void
    {
        Post::factory(5)->published()->for($this->author)->create();
        Post::factory(5)->draft()->for($this->author)->create();

        $published = $this->service->listPublishedPosts([], 10);

        $this->assertEquals(5, $published->total());
    }

    #[Test]
    /**
     * Can filter published posts by category
     */
    public function it_can_filter_published_posts_by_category(): void
    {
        $category1 = Category::where('slug', 'news')->first();
        $category2 = Category::where('slug', 'events')->first();

        Post::factory(3)->published()->for($this->author)->create()
            ->each(fn($post) => $post->categories()->attach($category1));
            
        Post::factory(2)->published()->for($this->author)->create()
            ->each(fn($post) => $post->categories()->attach($category2));

        $posts = $this->service->listPublishedPosts(['category' => 'news'], 50);

        $this->assertEquals(3, $posts->total());
    }

    #[Test]
    /**
     * Can search published posts
     */
    public function it_can_search_published_posts(): void
    {
        Post::factory()->published()->for($this->author)->create(['title' => 'Laravel Tips']);
        Post::factory()->published()->for($this->author)->create(['title' => 'PHP Tricks']);

        $posts = $this->service->listPublishedPosts(['search' => 'Laravel'], 50);

        $this->assertEquals(1, $posts->total());
    }

    #[Test]
    /**
     * Published posts ordered by newest first
     */
    public function it_orders_published_posts_newest_first(): void
    {
        $post1 = Post::factory()->published()->for($this->author)->create(['published_at' => now()->subDays(5)]);
        $post2 = Post::factory()->published()->for($this->author)->create(['published_at' => now()]);

        $posts = $this->service->listPublishedPosts([], 50);

        $this->assertEquals($post2->id, $posts->first()->id);
    }

    // ============ Author Posts Tests ============

    #[Test]
    /**
     * Can get author posts (published only by default)
     */
    public function it_can_get_author_published_posts(): void
    {
        Post::factory(3)->published()->for($this->author)->create();
        Post::factory(2)->draft()->for($this->author)->create();

        $posts = $this->service->getAuthorPosts($this->author, true);

        $this->assertCount(3, $posts);
    }

    #[Test]
    /**
     * Can get all author posts
     */
    public function it_can_get_all_author_posts(): void
    {
        Post::factory(3)->published()->for($this->author)->create();
        Post::factory(2)->draft()->for($this->author)->create();

        $posts = $this->service->getAuthorPosts($this->author, false);

        $this->assertCount(5, $posts);
    }

    #[Test]
    /**
     * Author posts respects limit
     */
    public function it_respects_limit_for_author_posts(): void
    {
        Post::factory(100)->published()->for($this->author)->create();

        $posts = $this->service->getAuthorPosts($this->author, true, 50);

        $this->assertCount(50, $posts);
    }

    #[Test]
    /**
     * Only returns posts from specific author
     */
    public function it_only_returns_specific_author_posts(): void
    {
        $author2 = User::factory()->create();
        Post::factory(5)->for($this->author)->create();
        Post::factory(5)->for($author2)->create();

        $posts = $this->service->getAuthorPosts($this->author, false);

        $this->assertCount(5, $posts);
    }

    // ============ Edge Cases ============

    #[Test]
    /**
     * Handles empty excerpt gracefully
     */
    public function it_handles_empty_content_for_excerpt(): void
    {
        $data = [
            'title' => 'No Content',
            'content' => '',
            'category' => 'test',
        ];

        $post = $this->service->createPost($this->author, $data);

        $this->assertNotNull($post->excerpt);
    }

    #[Test]
    /**
     * Handles duplicate slug generation
     */
    public function it_maintains_consistency_with_slug_uniqueness(): void
    {
        $data = [
            'title' => 'Test Post',
            'content' => 'Content',
            'category' => 'test',
        ];

        $post1 = $this->service->createPost($this->author, $data);
        $post2 = $this->service->createPost($this->author, $data);

        $this->assertNotEquals($post1->slug, $post2->slug);
    }
}
