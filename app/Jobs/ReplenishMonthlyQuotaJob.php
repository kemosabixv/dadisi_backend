<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReplenishMonthlyQuotaJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public bool $dryRun = false,
        public bool $force = false,
        public ?int $specificUserId = null
    ) {}

    /**
     * Execute the job to replenish monthly lab quota for users with active subscriptions.
     * 
     * Creates/replenishes monthly quota commitment for current month (idempotent).
     * Only processes users with active lab subscriptions.
     */
    public function handle(QuotaService $quotaService): void
    {
        Log::info('ReplenishMonthlyQuotaJob started', [
            'dry_run' => $this->dryRun,
            'force' => $this->force,
            'specific_user_id' => $this->specificUserId,
        ]);

        try {
            // Build query for users with active subscriptions
            $query = User::whereHas('subscriptions', function ($q) {
                $q->where('status', 'active')
                  ->whereNull('canceled_at')
                  ->where(function ($subQuery) {
                      $subQuery->whereNull('ends_at')
                          ->orWhere('ends_at', '>', now());
                  });
            });

            if ($this->specificUserId) {
                $query->where('id', $this->specificUserId);
            }

            $users = $query->with(['subscriptions.plan.systemFeatures'])->get();
            
            $replenished = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($users as $user) {
                try {
                    if ($this->dryRun) {
                        Log::info('Quota replenishment skipped for user (dry-run)', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                        ]);
                        $replenished++;
                        continue;
                    }

                    // Attempt to replenish quota
                    $result = $quotaService->replenishMonthlyQuota($user);

                    if ($result) {
                        $replenished++;
                        Log::info('Monthly quota replenished for user', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                        ]);
                    } else {
                        $skipped++;
                        Log::info('Quota replenishment skipped (already exists or no lab subscription)', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                        ]);
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Quota replenishment failed for user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('ReplenishMonthlyQuotaJob completed', [
                'replenished' => $replenished,
                'skipped' => $skipped,
                'failed' => $failed,
                'total_users' => $users->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('ReplenishMonthlyQuotaJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
