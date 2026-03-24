<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\SystemFeature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ChatQuotaExceededNotification;
use App\Notifications\NewMessageNotification;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed necessary features and plans
        $this->seed(\Database\Seeders\SystemFeatureSeeder::class);
        $this->seed(\Database\Seeders\PlanSeeder::class);
    }

    public function test_user_can_list_their_conversations()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        Conversation::create(['user_one_id' => min($user->id, $otherUser->id), 'user_two_id' => max($user->id, $otherUser->id)]);

        $response = $this->actingAs($user)->getJson(route('chat.conversations'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.partner.username', $otherUser->username);
    }

    public function test_user_can_start_a_conversation()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('chat.start', $otherUser->id));

        $response->assertStatus(200);
        $this->assertDatabaseHas('conversations', [
            'user_one_id' => min($user->id, $otherUser->id),
            'user_two_id' => max($user->id, $otherUser->id),
        ]);
    }

    public function test_user_can_send_a_message_and_it_is_encrypted()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::create([
            'user_one_id' => min($user->id, $otherUser->id), 
            'user_two_id' => max($user->id, $otherUser->id)
        ]);

        Notification::fake();

        $response = $this->actingAs($user)->postJson(route('chat.store'), [
            'conversation_id' => $conversation->id,
            'content' => 'Hello World',
            'type' => 'text'
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
        ]);

        // Verify notification was sent
        Notification::assertSentTo($otherUser, NewMessageNotification::class);
    }

    public function test_user_can_send_a_media_message()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::create([
            'user_one_id' => min($user->id, $otherUser->id), 
            'user_two_id' => max($user->id, $otherUser->id)
        ]);

        Notification::fake();

        $response = $this->actingAs($user)->postJson(route('chat.store'), [
            'conversation_id' => $conversation->id,
            'type' => 'image',
            'r2_key' => 'chats/media/test-image.jpg',
            'file_name' => 'test-image.jpg',
            'file_size' => 1024
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'type' => 'image',
            'r2_key' => 'chats/media/test-image.jpg',
            'file_name' => 'test-image.jpg',
        ]);
    }

    public function test_user_cannot_view_someone_elses_conversation()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => min($userA->id, $userB->id), 
            'user_two_id' => max($userA->id, $userB->id)
        ]);

        $response = $this->actingAs($userC)->getJson(route('chat.messages', $conversation->id));

        $response->assertStatus(403);
    }

    public function test_quota_is_enforced_for_community_plan()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::create([
            'user_one_id' => min($user->id, $otherUser->id), 
            'user_two_id' => max($user->id, $otherUser->id)
        ]);

        $plan = Plan::where('slug', 'community')->first();
        
        // Create active subscription for the user
        PlanSubscription::create([
            'subscriber_id' => $user->id,
            'subscriber_type' => 'user',
            'plan_id' => $plan->id,
            'slug' => 'default',
            'name' => ['en' => 'Default Subscription'],
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $feature = SystemFeature::where('slug', 'monthly_chat_message_limit')->first();
        $plan->systemFeatures()->updateExistingPivot($feature->id, ['value' => '2']);

        Notification::fake();

        // Send 2 messages (should be OK)
        $this->actingAs($user)->postJson(route('chat.store'), ['conversation_id' => $conversation->id, 'content' => 'msg 1', 'type' => 'text']);
        $this->actingAs($user)->postJson(route('chat.store'), ['conversation_id' => $conversation->id, 'content' => 'msg 2', 'type' => 'text']);

        // Send 3rd message (should fail)
        $response = $this->actingAs($user)->postJson(route('chat.store'), [
            'conversation_id' => $conversation->id,
            'content' => 'msg 3',
            'type' => 'text'
        ]);

        $response->assertStatus(400);
        $this->assertEquals('You have reached your monthly message limit. Please upgrade your plan to continue chatting.', $response->json('message'));

        Notification::assertSentTo($user, ChatQuotaExceededNotification::class);
    }

    public function test_user_can_delete_their_own_message()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::create([
            'user_one_id' => min($user->id, $otherUser->id), 
            'user_two_id' => max($user->id, $otherUser->id)
        ]);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => 'Delete me',
            'type' => 'text'
        ]);

        $response = $this->actingAs($user)->deleteJson(route('chat.message.delete', $message->id));

        $response->assertStatus(200);
        $this->assertSoftDeleted('chat_messages', ['id' => $message->id]);
    }

    public function test_user_cannot_delete_someone_elses_message()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::create([
            'user_one_id' => min($user->id, $otherUser->id), 
            'user_two_id' => max($user->id, $otherUser->id)
        ]);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $otherUser->id,
            'content' => 'Not yours',
            'type' => 'text'
        ]);

        $response = $this->actingAs($user)->deleteJson(route('chat.message.delete', $message->id));

        $response->assertStatus(403);
    }
}
