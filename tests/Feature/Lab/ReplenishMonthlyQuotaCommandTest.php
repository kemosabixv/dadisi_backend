<?php

namespace Tests\Feature\Lab;

use App\Console\Commands\ReplenishMonthlyQuotaCommand;
use App\Models\Plan;
use App\Models\QuotaCommitment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ReplenishMonthlyQuotaCommand Tests
 *
 * Verify the daily quota replenishment scheduler correctly:
 * 1. Finds only users with active lab subscriptions
 * 2. Skips users in grace period
 * 3. Skips subscriptions without lab quota feature
 * 4. Properly counts and logs results
 */
class ReplenishMonthlyQuotaCommandTest extends TestCase
{
    use RefreshDatabase;

    protected Plan $labPlan;

    protected Plan $nonLabPlan;

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

        // Create non-lab plan (no quota feature)
        $this->nonLabPlan = Plan::create([
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'price' => 1000,
            'currency' => 'KES',
            'billing_cycle' => 'monthly',
            'status' => 'active',
        ]);
        // No features attached
    }

    #[Test]
    public function test_command_replenishes_active_lab_subscriptions()
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        // Run command
        $this->artisan($this->getCommandSignature())
            ->assertExitCode(0);

        // Verify quota was created
        $commitment = QuotaCommitment::where('user_id', $user->id)
            ->where('month_date', now()->startOfMonth())
            ->first();

        $this->assertNotNull($commitment);
        $this->assertEquals(50, $commitment->committed_hours);
    }

    #[Test]
    public function test_command_skips_grace_period_subscriptions()
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),  // In grace period
            'canceled_at' => null,
        ]);

        // Run command
        $this->artisan($this->getCommandSignature())
            ->assertExitCode(0);

        // Verify NO quota was created
        $count = QuotaCommitment::where('user_id', $user->id)->count();
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function test_command_skips_canceled_subscriptions()
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'canceled_at' => now()->subDay(),
        ]);

        // Run command
        $this->artisan($this->getCommandSignature())
            ->assertExitCode(0);

        // Verify NO quota was created
        $count = QuotaCommitment::where('user_id', $user->id)->count();
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function test_command_skips_non_lab_subscriptions()
    {
        $user = User::factory()->create();
        $subscription = $user->subscriptions()->create([
            'plan_id' => $this->nonLabPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        // Run command
        $this->artisan($this->getCommandSignature())
            ->assertExitCode(0);

        // Verify NO quota was created
        $count = QuotaCommitment::where('user_id', $user->id)->count();
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function test_command_counts_replenished_skipped_correctly()
    {
        // Create 3 users: active lab, in grace period, non-lab
        $activeUser = User::factory()->create();
        $activeUser->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        $gracePeriodUser = User::factory()->create();
        $gracePeriodUser->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
            'canceled_at' => null,
        ]);

        $nonLabUser = User::factory()->create();
        $nonLabUser->subscriptions()->create([
            'plan_id' => $this->nonLabPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        // Run command
        $this->artisan($this->getCommandSignature())
            ->assertExitCode(0);

        // Active user should have quota
        $this->assertEquals(1, QuotaCommitment::where('user_id', $activeUser->id)->count());

        // Grace period and non-lab users should NOT have quota
        $this->assertEquals(0, QuotaCommitment::where('user_id', $gracePeriodUser->id)->count());
        $this->assertEquals(0, QuotaCommitment::where('user_id', $nonLabUser->id)->count());
    }

    #[Test]
    public function test_command_with_user_option()
    {
        $user1 = User::factory()->create();
        $user1->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        $user2 = User::factory()->create();
        $user2->subscriptions()->create([
            'plan_id' => $this->labPlan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
        ]);

        // Run command for only user1
        $this->artisan($this->getCommandSignature(), ['--user' => $user1->id])
            ->assertExitCode(0);

        // Only user1 should have quota
        $this->assertEquals(1, QuotaCommitment::where('user_id', $user1->id)->count());
        $this->assertEquals(0, QuotaCommitment::where('user_id', $user2->id)->count());
    }

    protected function getCommandSignature(): string
    {
        return 'quota:replenish-monthly';
    }
}
