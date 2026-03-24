<?php

namespace Tests\Feature\Lab;

use App\Models\Plan;
use App\Models\QuotaCommitment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $labPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create lab plan with quota
        $this->labPlan = Plan::create([
            'name' => 'Lab Plan',
            'slug' => 'lab',
            'price' => 5000,
            'currency' => 'KES',
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);
        $labFeature = \App\Models\SystemFeature::firstOrCreate([
            'slug' => 'lab_hours_monthly',
        ], [
            'name' => 'Lab Quota Hours',
            'description' => 'Monthly lab quota hours',
        ]);
        $this->labPlan->systemFeatures()->attach($labFeature->id, ['value' => 50]);
    }

    public function test_debug_command_output()
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        // Check if user has subscription
        $this->assertCount(1, $user->subscriptions);
        
        // Check if user has active lab subscription
        $activeSub = $user->activeLabSubscription();
        
        // Write debug info
        $debugPath = base_path('storage/logs/debug_test.log');
        file_put_contents($debugPath, json_encode([
            'user_id' => $user->id,
            'subscriptions_count' => $user->subscriptions()->count(),
            'active_lab_subscription' => $activeSub ? $activeSub->id : null,
            'plan_id' => $activeSub?->plan_id,
            'feature_value' => $activeSub?->plan?->getFeatureValue('lab_hours_monthly'),
        ], JSON_PRETTY_PRINT));
        
        // Run command and capture output
        $this->artisan('quota:replenish-monthly')
            ->assertExitCode(0);
            
        $this->assertTrue(true); // placeholder 
    }
}
