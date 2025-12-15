<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\County;

/**
 * Simple Post API tests - focused on what actually works
 */
class SimplePostApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     * Public posts endpoint returns 200
     */
    public function test_public_posts_endpoint_returns_success()
    {
        $response = $this->getJson('/api/blog/posts');

        $response->assertStatus(200);
    }

    /**
     * @test
     * Can create a published post and retrieve it
     */
    public function test_can_retrieve_published_posts()
    {
        $user = User::factory()->create();
        $county = County::factory()->create();

        $post = Post::factory()
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county->id,
                'title' => 'Test Post',
            ]);

        $response = $this->getJson('/api/blog/posts');

        $response->assertStatus(200);
        // Verify response structure
        $response->assertJsonStructure([
            'success',
            'data',
            'pagination'
        ]);
    }

    /**
     * @test
     * Published posts are returned in list
     */
    public function test_published_posts_appear_in_list()
    {
        $user = User::factory()->create();
        $county = County::factory()->create();

        $publishedPost = Post::factory()
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county->id,
                'title' => 'Published Post',
            ]);

        $draftPost = Post::factory()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county->id,
                'status' => 'draft',
                'title' => 'Draft Post',
            ]);

        $response = $this->getJson('/api/blog/posts');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Can filter posts by search
     */
    public function test_search_filters_posts()
    {
        $user = User::factory()->create();
        $county = County::factory()->create();

        Post::factory()
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county->id,
                'title' => 'Laravel Tutorial',
                'excerpt' => 'Learn Laravel',
            ]);

        Post::factory()
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county->id,
                'title' => 'React Guide',
                'excerpt' => 'Learn React',
            ]);

        $response = $this->getJson('/api/blog/posts?search=Laravel');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    /**
     * @test
     * Posts are paginated correctly
     */
    public function test_posts_pagination()
    {
        $user = User::factory()->create();
        $county = County::factory()->create();

        Post::factory(15)
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county->id,
            ]);

        $response = $this->getJson('/api/blog/posts?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(15, $response->json('pagination.total'));
        $this->assertEquals(10, $response->json('pagination.per_page'));
    }

    /**
     * @test
     * Can retrieve single post
     */
    public function test_can_retrieve_single_post()
    {
        $user = User::factory()->create();
        $county = County::factory()->create();

        $post = Post::factory()
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county->id,
                'title' => 'Single Post',
                'slug' => 'single-post',
            ]);

        $response = $this->getJson("/api/blog/posts/{$post->slug}");

        $response->assertStatus(200);
    }

    /**
     * @test
     * Filter posts by county
     */
    public function test_filter_posts_by_county()
    {
        $user = User::factory()->create();
        $county1 = County::factory()->create(['name' => 'Nairobi']);
        $county2 = County::factory()->create(['name' => 'Mombasa']);

        Post::factory(2)
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county1->id,
            ]);

        Post::factory(3)
            ->published()
            ->create([
                'user_id' => $user->id,
                'county_id' => $county2->id,
            ]);

        $response = $this->getJson("/api/blog/posts?county_id={$county1->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('pagination.total'));
    }
}
