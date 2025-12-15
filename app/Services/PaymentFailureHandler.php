<?php

namespace App\Services;

use App\Models\SubscriptionEnhancement;
use App\Models\AutoRenewalJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentFailedFinalMail;

class PaymentFailureHandler
{
    /**
     * Handle a payment failure: update enhancement, schedule next retry, and log.
     */
    public static function handle(SubscriptionEnhancement $enh, AutoRenewalJob $job, array $result = []): void
    {
        try {
            // Prioritize legacy renewal_attempts if non-zero; otherwise use newer renewal_attempt_count
            $baseAttempts = ($enh->renewal_attempts && $enh->renewal_attempts > 0) 
                ? $enh->renewal_attempts 
                : ($enh->renewal_attempt_count ?? 0);
            $attempts = $baseAttempts + 1;

            $enh->renewal_attempt_count = $attempts;
            $enh->last_renewal_attempt_at = now();
            $enh->last_renewal_result = 'failed';
            $enh->last_renewal_error = $result['error_message'] ?? null;

            // decide next retry schedule
            $next = null;
            if ($attempts === 1) {
                $next = now()->addDay();
                $job->attempt_type = 'retry_24h';
            } elseif ($attempts === 2) {
                $next = now()->addDays(3);
                $job->attempt_type = 'retry_3d';
            } else {
                $next = now()->addDays(7);
                $job->attempt_type = 'retry_7d';
            }

            $job->next_retry_at = $next;
            $enh->next_auto_renewal_at = $next;

            $enh->save();
            $job->save();

            // Notify user/admin when final failure threshold reached (e.g., attempts >= 3)
            if ($attempts >= 3) {
                try {
                    $user = $enh->subscription?->subscriber ?? null;

                    // Fallback: use job.user_id if subscriber not available (tests may set relation on subscription instance only)
                    if (!$user && !empty($job->user_id)) {
                        $user = \App\Models\User::find($job->user_id);
                    }

                    if ($user && filter_var($user->email ?? '', FILTER_VALIDATE_EMAIL)) {
                        Mail::to($user->email)->queue(new PaymentFailedFinalMail($enh, $job));
                    }
                } catch (\Exception $e) {
                    Log::error('PaymentFailureHandler: failed to send final-failure email', ['error' => $e->getMessage(), 'enhancement_id' => $enh->id]);
                }
            }
        } catch (\Exception $e) {
            Log::error('PaymentFailureHandler failed', ['error' => $e->getMessage(), 'enhancement_id' => $enh->id]);
        }
    }
}
