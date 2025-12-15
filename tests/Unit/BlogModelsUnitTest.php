<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\County;

class BlogModelsUnitTest extends TestCase
{
    use RefreshDatabase;

    private User $author;
    private Category $category;
    private Tag $tag;
    private County $county;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $this->category = Category::factory()->create();
        $this->tag = Tag::factory()->create();
        $this->county = County::factory()->create();
    }

    /**
     * Post Model Tests
     */

    /**
     * @test
     * Post belongs to author (user)
     */
    public function test_post_belongs_to_author()
    {
        $post = Post::factory()->create([
            'user_id' => $this->author->id,
        ]);

        $this->assertEquals($this->author->id, $post->author->id);
    }

    /**
     * @test
     * Post belongs to county
     */
    public function test_post_belongs_to_county()
    {
        $post = Post::factory()->create([
            'county_id' => $this->county->id,
        ]);

        $this->assertEquals($this->county->id, $post->county->id);
    }

    /**
     * @test
     * Post has many categories (belongsToMany)
     */
    public function test_post_has_many_categories()
    {
        $post = Post::factory()->create();

        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $post->categories()->attach([$category1->id, $category2->id]);

        $this->assertEquals(2, $post->categories()->count());
        $this->assertContains($category1->id, $post->categories->pluck('id')->toArray());
        $this->assertContains($category2->id, $post->categories->pluck('id')->toArray());
    }

    /**
     * @test
     * Post has many tags (belongsToMany)
     */
    public function test_post_has_many_tags()
    {
        $post = Post::factory()->create();

        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $post->tags()->attach([$tag1->id, $tag2->id]);

        $this->assertEquals(2, $post->tags()->count());
    }

    /**
     * @test
     * Post published scope returns only published posts
     */
    public function test_post_published_scope()
    {
        $publishedPost = Post::factory()->create(['status' => 'published']);
        $draftPost = Post::factory()->create(['status' => 'draft']);

        $published = Post::published()->get();

        $this->assertContains($publishedPost->id, $published->pluck('id')->toArray());
        $this->assertNotContains($draftPost->id, $published->pluck('id')->toArray());
    }

    /**
     * @test
     * Post draft scope returns only draft posts
     */
    public function test_post_draft_scope()
    {
        $publishedPost = Post::factory()->create(['status' => 'published']);
        $draftPost = Post::factory()->create(['status' => 'draft']);

        $drafts = Post::draft()->get();

        $this->assertNotContains($publishedPost->id, $drafts->pluck('id')->toArray());
        $this->assertContains($draftPost->id, $drafts->pluck('id')->toArray());
    }

    /**
     * @test
     * Post featured scope returns only featured posts
     */
    public function test_post_featured_scope()
    {
        $featuredPost = Post::factory()->create(['is_featured' => true]);
        $regularPost = Post::factory()->create(['is_featured' => false]);

        $featured = Post::featured()->get();

        $this->assertContains($featuredPost->id, $featured->pluck('id')->toArray());
        $this->assertNotContains($regularPost->id, $featured->pluck('id')->toArray());
    }

    /**
     * @test
     * Post generates slug automatically
     */
    public function test_post_generates_slug()
    {
        $post = Post::factory()->create([
            'title' => 'Getting Started With Laravel',
        ]);

        $this->assertNotEmpty($post->slug);
        $this->assertStringContainsString('getting-started', strtolower($post->slug));
    }

    /**
     * @test
     * Post slug is unique
     */
    public function test_post_slug_uniqueness()
    {
        $post1 = Post::factory()->create([
            'title' => 'Test Post',
        ]);

        $post2 = Post::factory()->create([
            'title' => 'Test Post',
        ]);

        $this->assertNotEquals($post1->slug, $post2->slug);
    }

    /**
     * @test
     * Post has default status of draft
     */
    public function test_post_default_status_is_draft()
    {
        $post = Post::factory()->create();

        $this->assertEquals('draft', $post->status);
    }

    /**
     * @test
     * Post is_featured defaults to false
     */
    public function test_post_is_featured_defaults_to_false()
    {
        $post = Post::factory()->create();

        $this->assertFalse($post->is_featured);
    }

    /**
     * @test
     * Post views_count can be incremented
     */
    public function test_post_views_count_increment()
    {
        $post = Post::factory()->create(['views_count' => 10]);

        $post->increment('views_count');

        $this->assertEquals(11, $post->fresh()->views_count);
    }

    /**
     * @test
     * Post excerpt is truncated version of body
     */
    public function test_post_excerpt_truncation()
    {
        $longContent = str_repeat('Lorem ipsum dolor sit amet. ', 50);
        $post = Post::factory()->create([
            'body' => $longContent,
            'excerpt' => substr($longContent, 0, 200),
        ]);

        $this->assertNotEmpty($post->excerpt);
        $this->assertLessThanOrEqual(200, strlen($post->excerpt));
    }

    /**
     * @test
     * Post published_at timestamp is set when publishing
     */
    public function test_post_published_at_timestamp()
    {
        $now = now();
        $post = Post::factory()->create([
            'status' => 'published',
            'published_at' => $now,
        ]);

        $this->assertNotNull($post->published_at);
        $this->assertEquals($now->format('Y-m-d'), $post->published_at->format('Y-m-d'));
    }

    /**
     * @test
     * Post uses soft deletes
     */
    public function test_post_uses_soft_deletes()
    {
        $post = Post::factory()->create();

        $post->delete();

        $this->assertTrue($post->trashed());
        $this->assertSoftDeleted($post);
    }

    /**
     * @test
     * Deleted posts are not returned in queries
     */
    public function test_deleted_posts_not_in_queries()
    {
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();

        $post1->delete();

        $posts = Post::all();

        $this->assertEquals(1, $posts->count());
        $this->assertContains($post2->id, $posts->pluck('id')->toArray());
        $this->assertNotContains($post1->id, $posts->pluck('id')->toArray());
    }

    /**
     * @test
     * Post can be restored from soft delete
     */
    public function test_post_can_be_restored()
    {
        $post = Post::factory()->create();
        $post->delete();

        $post->restore();

        $this->assertFalse($post->trashed());
        $this->assertNotNull(Post::find($post->id));
    }

    /**
     * Category Model Tests
     */

    /**
     * @test
     * Category has many posts
     */
    public function test_category_has_many_posts()
    {
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();

        $this->category->posts()->attach([$post1->id, $post2->id]);

        $this->assertEquals(2, $this->category->posts()->count());
    }

    /**
     * @test
     * Category slug is generated from name
     */
    public function test_category_slug_generation()
    {
        $category = Category::factory()->create([
            'name' => 'Technology and News',
        ]);

        $this->assertNotEmpty($category->slug);
    }

    /**
     * Tag Model Tests
     */

    /**
     * @test
     * Tag has many posts
     */
    public function test_tag_has_many_posts()
    {
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();

        $this->tag->posts()->attach([$post1->id, $post2->id]);

        $this->assertEquals(2, $this->tag->posts()->count());
    }

    /**
     * @test
     * Tag slug is generated from name
     */
    public function test_tag_slug_generation()
    {
        $tag = Tag::factory()->create([
            'name' => 'Laravel Framework',
        ]);

        $this->assertNotEmpty($tag->slug);
    }

    /**
     * County Model Tests
     */

    /**
     * @test
     * County has many posts
     */
    public function test_county_has_many_posts()
    {
        $post1 = Post::factory()->create([
            'county_id' => $this->county->id,
        ]);
        $post2 = Post::factory()->create([
            'county_id' => $this->county->id,
        ]);

        $this->assertEquals(2, $this->county->posts()->count());
    }

    /**
     * User Model - Blog Posts Tests
     */

    /**
     * @test
     * User has many posts as author
     */
    public function test_user_has_many_posts()
    {
        Post::factory(3)->create([
            'author_id' => $this->author->id,
        ]);

        $this->assertEquals(3, $this->author->posts()->count());
    }

    /**
     * @test
     * User can have posts in different statuses
     */
    public function test_user_can_have_mixed_status_posts()
    {
        Post::factory(2)->create([
            'author_id' => $this->author->id,
            'status' => 'draft',
        ]);

        Post::factory(3)->create([
            'author_id' => $this->author->id,
            'status' => 'published',
        ]);

        $this->assertEquals(5, $this->author->posts()->count());
        $this->assertEquals(2, $this->author->posts()->where('status', 'draft')->count());
        $this->assertEquals(3, $this->author->posts()->where('status', 'published')->count());
    }

    /**
     * Blog Query and Filter Tests
     */

    /**
     * @test
     * Can filter posts by title
     */
    public function test_filter_posts_by_title()
    {
        Post::factory()->create(['title' => 'Laravel Tutorial']);
        Post::factory()->create(['title' => 'Vue Guide']);

        $posts = Post::where('title', 'like', '%Laravel%')->get();

        $this->assertEquals(1, $posts->count());
        $this->assertStringContainsString('Laravel', $posts->first()->title);
    }

    /**
     * @test
     * Can filter posts by content
     */
    public function test_filter_posts_by_content()
    {
        Post::factory()->create(['body' => 'This is about database queries']);
        Post::factory()->create(['body' => 'This is about HTTP requests']);

        $posts = Post::where('body', 'like', '%database%')->get();

        $this->assertEquals(1, $posts->count());
    }

    /**
     * @test
     * Can order posts by published date
     */
    public function test_order_posts_by_published_date()
    {
        $post1 = Post::factory()->create([
            'published_at' => now()->subDays(10),
        ]);

        $post2 = Post::factory()->create([
            'published_at' => now(),
        ]);

        $latestFirst = Post::orderByDesc('published_at')->get();

        $this->assertEquals($post2->id, $latestFirst->first()->id);
        $this->assertEquals($post1->id, $latestFirst->last()->id);
    }

    /**
     * @test
     * Can order posts by views count
     */
    public function test_order_posts_by_views()
    {
        Post::factory()->create(['views_count' => 50]);
        Post::factory()->create(['views_count' => 150]);
        Post::factory()->create(['views_count' => 100]);

        $ordered = Post::orderByDesc('views_count')->get();

        $this->assertEquals(150, $ordered->first()->views_count);
        $this->assertEquals(50, $ordered->last()->views_count);
    }

    /**
     * @test
     * Can get posts by category with eager loading
     */
    public function test_get_posts_by_category_with_eager_loading()
    {
        $post = Post::factory()->create();
        $post->categories()->attach($this->category);

        $posts = Post::whereHas('categories', function ($q) {
            $q->where('category_id', $this->category->id);
        })->with('categories')->get();

        $this->assertEquals(1, $posts->count());
        $this->assertContains($this->category->id, $posts->first()->categories->pluck('id')->toArray());
    }

    /**
     * @test
     * Can get posts by tag with eager loading
     */
    public function test_get_posts_by_tag_with_eager_loading()
    {
        $post = Post::factory()->create();
        $post->tags()->attach($this->tag);

        $posts = Post::whereHas('tags', function ($q) {
            $q->where('tag_id', $this->tag->id);
        })->with('tags')->get();

        $this->assertEquals(1, $posts->count());
        $this->assertContains($this->tag->id, $posts->first()->tags->pluck('id')->toArray());
    }

    /**
     * @test
     * Featured posts query works correctly
     */
    public function test_featured_posts_query()
    {
        Post::factory(3)->create(['is_featured' => true]);
        Post::factory(2)->create(['is_featured' => false]);

        $featured = Post::where('is_featured', true)->get();

        $this->assertEquals(3, $featured->count());
    }

    /**
     * @test
     * Published posts with author relation
     */
    public function test_published_posts_with_author()
    {
        Post::factory()->create([
            'status' => 'published',
            'author_id' => $this->author->id,
        ]);

        $posts = Post::published()->with('author')->get();

        $this->assertEquals(1, $posts->count());
        $this->assertEquals($this->author->id, $posts->first()->author->id);
    }

    /**
     * Post Relationships Tests
     */

    /**
     * @test
     * Post can be associated with multiple categories and tags
     */
    public function test_post_multiple_associations()
    {
        $post = Post::factory()->create();

        $categories = Category::factory(3)->create();
        $tags = Tag::factory(3)->create();

        $post->categories()->attach($categories->pluck('id'));
        $post->tags()->attach($tags->pluck('id'));

        $this->assertEquals(3, $post->categories()->count());
        $this->assertEquals(3, $post->tags()->count());
    }

    /**
     * @test
     * Detaching category from post
     */
    public function test_detach_category_from_post()
    {
        $post = Post::factory()->create();
        $category = Category::factory()->create();

        $post->categories()->attach($category);
        $this->assertEquals(1, $post->categories()->count());

        $post->categories()->detach($category);
        $this->assertEquals(0, $post->categories()->count());
    }

    /**
     * @test
     * Syncing categories replaces all existing associations
     */
    public function test_sync_categories_replaces_existing()
    {
        $post = Post::factory()->create();
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();
        $cat3 = Category::factory()->create();

        $post->categories()->attach([$cat1->id, $cat2->id]);
        $this->assertEquals(2, $post->categories()->count());

        $post->categories()->sync([$cat3->id]);

        $this->assertEquals(1, $post->categories()->count());
        $this->assertContains($cat3->id, $post->categories->pluck('id')->toArray());
        $this->assertNotContains($cat1->id, $post->categories->pluck('id')->toArray());
    }
}
