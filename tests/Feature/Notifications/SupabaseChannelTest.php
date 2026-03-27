<?php

namespace Tests\Feature\Notifications;

use App\Channels\SupabaseChannel;
use App\Models\User;
use App\Services\SupabaseRealtimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock configurations
        config(['services.supabase.url' => 'https://mock-supabase.com']);
        config(['services.supabase.service_key' => 'mock-key']);
        config(['services.supabase.table' => 'mock_table']);
    }

    /**
     * Test notification is sent over SupabaseChannel for a user.
     */
    public function test_notification_sent_over_supabase_channel()
    {
        Http::fake();
        $user = User::factory()->create();
        
        $notification = new class extends Notification {
            public function toSupabase($notifiable)
            {
                return [
                    'recipient_type' => 'user',
                    'title' => 'User Title',
                    'message' => 'User Message',
                    'type' => 'test_user_notif'
                ];
            }
        };

        $channel = new SupabaseChannel();
        $channel->send($user, $notification);

        Http::assertSent(function ($request) use ($user) {
            return $request['user_id'] == $user->id &&
                   $request['recipient_type'] == 'user' &&
                   $request['title'] == 'User Title';
        });
    }

    /**
     * Test notification is sent over SupabaseChannel for staff.
     */
    public function test_staff_notification_sent_over_supabase_channel()
    {
        Http::fake();
        $user = User::factory()->create(); // Actor submitting something
        
        $notification = new class extends Notification {
            public function toSupabase($notifiable)
            {
                return [
                    'recipient_type' => 'staff',
                    'permission' => 'can_manage_users',
                    'title' => 'Staff Title',
                    'message' => 'Staff Message',
                    'type' => 'test_staff_notif'
                ];
            }
        };

        $channel = new SupabaseChannel();
        $channel->send($user, $notification);

        Http::assertSent(function ($request) {
            return $request['recipient_type'] == 'staff' &&
                   $request['user_id'] == null &&
                   $request['permission'] == 'can_manage_users' &&
                   $request['title'] == 'Staff Title';
        });
    }

    /**
     * Test channel skips if toSupabase is missing.
     */
    public function test_channel_skips_if_toSupabase_missing()
    {
        Http::fake();
        $user = User::factory()->create();
        $notification = new class extends Notification {};

        $channel = new SupabaseChannel();
        $channel->send($user, $notification);
        Http::assertNothingSent();
    }

    /**
     * Test extra metadata is captured into the data column.
     */
    public function test_extra_metadata_captured_into_data_column()
    {
        Http::fake();
        $user = User::factory()->create();
        
        $notification = new class extends Notification {
            public function toSupabase($notifiable)
            {
                return [
                    'recipient_type' => 'user',
                    'title' => 'Title',
                    'message' => 'Message',
                    'type' => 'test_extra',
                    'extra_field' => 'extra_value',
                    'refund_id' => 456
                ];
            }
        };

        $channel = new SupabaseChannel();
        $channel->send($user, $notification);

        Http::assertSent(function ($request) {
            return $request['recipient_type'] == 'user' &&
                   isset($request['data']['extra_field']) &&
                   $request['data']['extra_field'] == 'extra_value' &&
                   isset($request['data']['refund_id']) &&
                   $request['data']['refund_id'] == 456;
        });
    }
}
