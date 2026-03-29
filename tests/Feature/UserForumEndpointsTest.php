<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UserForumEndpointsTest
 *
 * Tests the user-specific forum activity endpoints:
 * - /api/user/forum/threads
 * - /api/user/forum/posts
 * - Role filtering in /api/forum/users
 */
class UserForumEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private User $moderator;
    private ForumThread $thread1;
    private ForumThread $thread2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

        // Create verified users (ForumUserService strictly requires email_verified_at)
        $this->user1 = User::factory()->create(['email_verified_at' => now()]);
        $this->user2 = User::factory()->create(['email_verified_at' => now()]);
        $this->moderator = User::factory()->create(['email_verified_at' => now()]);
        
        // Let's create the roles just in case they aren't seeded identically
        if (!Role::where('name', 'moderator')->exists()) {
            Role::create(['name' => 'moderator']);
        }
        $this->moderator->assignRole('moderator');

        $category = ForumCategory::factory()->create(['name' => 'General']);

        // User 1 creates a thread
        $this->thread1 = ForumThread::factory()->create([
            'category_id' => $category->id,
            'user_id' => $this->user1->id,
            'title' => 'Thread 1',
        ]);

        // User 2 creates a thread
        $this->thread2 = ForumThread::factory()->create([
            'category_id' => $category->id,
            'user_id' => $this->user2->id,
            'title' => 'Thread 2',
        ]);

        // User 1 creates a post in Thread 2
        ForumPost::factory()->create([
            'thread_id' => $this->thread2->id,
            'user_id' => $this->user1->id,
            'content' => 'Post by User 1',
        ]);

        // User 2 creates a post in Thread 1
        ForumPost::factory()->create([
            'thread_id' => $this->thread1->id,
            'user_id' => $this->user2->id,
            'content' => 'Post by User 2',
        ]);
    }

    #[Test]
    public function guests_cannot_access_user_forum_activity(): void
    {
        $this->getJson('/api/user/forum/threads')->assertStatus(401);
        $this->getJson('/api/user/forum/posts')->assertStatus(401);
    }

    #[Test]
    public function users_can_view_only_their_own_threads(): void
    {
        $response = $this->actingAs($this->user1)->getJson('/api/user/forum/threads');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['title' => 'Thread 1']);
        $response->assertJsonMissing(['title' => 'Thread 2']);
    }

    #[Test]
    public function users_can_view_only_their_own_replies(): void
    {
        $response = $this->actingAs($this->user2)->getJson('/api/user/forum/posts');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonFragment(['content' => 'Post by User 2']);
        $response->assertJsonMissing(['content' => 'Post by User 1']);
    }

    #[Test]
    public function forum_users_directory_can_be_filtered_by_role(): void
    {
        $response = $this->getJson('/api/forum/users?role=moderator');

        $response->assertStatus(200);

        // Assert that the moderator is in the response data
        $response->assertJsonFragment(['username' => $this->moderator->username]);
        
        // Assert that regular users are NOT returned when filtered by role
        $response->assertJsonMissing(['username' => $this->user1->username]);
        $response->assertJsonMissing(['username' => $this->user2->username]);
    }
}
