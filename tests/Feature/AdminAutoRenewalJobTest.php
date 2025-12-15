<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Plan;
use App\Models\PlanSubscription;
use App\Models\AutoRenewalJob;

class AdminAutoRenewalJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_retry_and_cancel_jobs()
    {
        $admin = User::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($admin, 'sanctum');

        $user = User::create([
            'username' => 'u1',
            'email' => 'u1@example.com',
            'password' => bcrypt('password'),
        ]);

        $plan = Plan::create([
            'name' => ['en' => 'Plan A'],
            'slug' => 'plan-a',
            'price' => 500,
            'currency' => 'KES',
        ]);

        $subscription = PlanSubscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => ['en' => 'Sub A'],
            'slug' => 'sub-a-' . uniqid(),
            'starts_at' => now(),
        ]);

        $job = AutoRenewalJob::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'status' => 'failed',
            'attempt_type' => 'initial',
            'attempt_number' => 1,
            'max_attempts' => 3,
            'scheduled_at' => now(),
            'amount' => 500,
            'currency' => 'KES',
        ]);

        // retry
        $resp = $this->postJson('/api/admin/auto-renewal-jobs/' . $job->id . '/retry');
        $resp->assertStatus(200)->assertJson(['success' => true]);

        // cancel
        $resp2 = $this->postJson('/api/admin/auto-renewal-jobs/' . $job->id . '/cancel');
        $resp2->assertStatus(200)->assertJson(['success' => true]);
    }
}
