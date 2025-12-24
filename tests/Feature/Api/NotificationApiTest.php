<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }

    public function test_user_can_list_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'current_page',
                    'last_page',
                ],
                'unread_count',
            ]);
    }

    public function test_user_can_get_unread_count(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'unread_count',
                ],
            ]);
    }

    public function test_user_can_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_user_can_clear_all_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/notifications/clear-all');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_mark_nonexistent_notification_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/notifications/nonexistent-uuid/read');

        $response->assertStatus(404);
    }

    public function test_delete_nonexistent_notification_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson('/api/notifications/nonexistent-uuid');

        $response->assertStatus(404);
    }
}
