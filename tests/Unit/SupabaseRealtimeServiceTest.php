<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SupabaseRealtimeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class SupabaseRealtimeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock configurations
        Config::set('services.supabase.url', 'https://mock-supabase.com');
        Config::set('services.supabase.service_key', 'mock-key');
        Config::set('services.supabase.table', 'mock_table');
    }

    /**
     * Test successful notification push to Supabase.
     */
    public function test_push_successful_request()
    {
        Http::fake([
            'https://mock-supabase.com/rest/v1/mock_table' => Http::response([], 201)
        ]);

        $payload = [
            'type' => 'test_notif',
            'title' => 'Test Title',
            'message' => 'Test Message',
            'link' => '/test-link',
            'data' => ['key' => 'value']
        ];

        SupabaseRealtimeService::push(
            123, 
            'user', 
            $payload
        );

        Http::assertSent(function ($request) use ($payload) {
            return $request->url() == 'https://mock-supabase.com/rest/v1/mock_table' &&
                   $request->method() == 'POST' &&
                   $request->hasHeader('apikey', 'mock-key') &&
                   $request->hasHeader('Authorization', 'Bearer mock-key') &&
                   $request['user_id'] == 123 &&
                   $request['recipient_type'] == 'user' &&
                   $request['title'] == 'Test Title' &&
                   $request['message'] == 'Test Message' &&
                   $request['link'] == '/test-link' &&
                   $request['data'] == ['key' => 'value'];
        });
    }

    /**
     * Test push for staff targeted notification.
     */
    public function test_push_staff_notification()
    {
        Http::fake([
            'https://mock-supabase.com/rest/v1/mock_table' => Http::response([], 201)
        ]);

        $payload = [
            'type' => 'staff_alert',
            'title' => 'Admin Alert',
            'message' => 'Action required',
        ];

        SupabaseRealtimeService::push(
            null, 
            'staff', 
            $payload,
            'admin',
            'can_manage_users'
        );

        Http::assertSent(function ($request) {
            return $request['recipient_type'] == 'staff' &&
                   $request['user_id'] == null &&
                   $request['role'] == 'admin' &&
                   $request['permission'] == 'can_manage_users';
        });
    }

    /**
     * Test push skips if config is missing.
     */
    public function test_push_skips_if_config_missing()
    {
        Config::set('services.supabase.url', null);
        Http::fake();

        SupabaseRealtimeService::push(1, 'user', ['title' => 'test']);

        Http::assertNothingSent();
    }
}
