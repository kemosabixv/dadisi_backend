<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\StudentApprovalRequest;
use App\Models\SubscriptionEnhancement;
use App\Models\User;
use App\Notifications\StudentApprovalApproved;
use App\Notifications\StudentApprovalRejected;
use App\Notifications\StudentApprovalSubmitted;
use App\Services\Contracts\StudentApprovalServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Student Approval Service
 *
 * Handles student plan approval workflow including submission, review, and subscription activation.
 */
class StudentApprovalService implements StudentApprovalServiceContract
{
    /**
     * Submit student approval request
     */
    public function submitApprovalRequest(int $userId, array $data): StudentApprovalRequest
    {
        try {
            $approvalRequest = DB::transaction(function () use ($userId, $data) {
                // Check for existing active request
                $existingRequest = StudentApprovalRequest::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->first();

                if ($existingRequest) {
                    throw new \Exception('You already have an active student approval request');
                }

                $approvalRequest = StudentApprovalRequest::create([
                    'user_id' => $userId,
                    'status' => 'pending',
                    'student_institution' => $data['student_institution'],
                    'student_email' => $data['student_email'],
                    'documentation_url' => $data['documentation_url'],
                    'student_birth_date' => $data['birth_date'],
                    'county' => $data['county'],
                    'additional_notes' => $data['additional_notes'] ?? null,
                    'requested_at' => now(),
                    'expires_at' => now()->addDays(30),
                ]);

                Log::info('Student approval request submitted', [
                    'user_id' => $userId,
                    'request_id' => $approvalRequest->id,
                    'institution' => $data['student_institution'],
                ]);

                return $approvalRequest;
            });

            // Notify staff members with approval permission (outside transaction)
            try {
                $staffToNotify = User::permission('approve_student_approvals')->get();
                if ($staffToNotify->isNotEmpty()) {
                    Notification::send($staffToNotify, new StudentApprovalSubmitted($approvalRequest));
                    Log::info('Student approval notifications sent', [
                        'request_id' => $approvalRequest->id,
                        'staff_count' => $staffToNotify->count(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send student approval notifications', [
                    'error' => $e->getMessage(),
                    'request_id' => $approvalRequest->id,
                ]);
                // Don't fail the request if notification fails
            }

            return $approvalRequest;
        } catch (\Exception $e) {
            Log::error('Student approval request submission failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get user's most recent approval status
     */
    public function getApprovalStatus(int $userId): ?StudentApprovalRequest
    {
        try {
            return StudentApprovalRequest::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve approval status', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get specific approval request details (with authorization check)
     */
    public function getApprovalDetails($requestId): StudentApprovalRequest
    {
        try {
            return StudentApprovalRequest::with('user.memberProfile')->findOrFail($requestId);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve approval details', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * List approval requests (admin)
     */
    public function listApprovalRequests(array $filters = []): LengthAwarePaginator
    {
        try {
            $query = StudentApprovalRequest::with('user.memberProfile');

            if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
                $query->where('status', $filters['status']);
            } elseif (! array_key_exists('status', $filters)) {
                // Default to pending ONLY if status is not provided at all in the array
                $query->where('status', 'pending');
            }
            // If status is 'all' or empty string, we don't apply where('status') filter, showing all

            if (! empty($filters['county'])) {
                $query->where('county', 'LIKE', '%'.$filters['county'].'%');
            }

            return $query->orderBy('requested_at', 'asc')
                ->paginate($filters['per_page'] ?? 15);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve approval requests', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Approve student request and create/activate subscription
     */
    public function approveRequest($requestId, int $adminId, ?string $adminNotes = null): StudentApprovalRequest
    {
        try {
            $approvalRequest = DB::transaction(function () use ($requestId, $adminId, $adminNotes) {
                $approvalRequest = StudentApprovalRequest::findOrFail($requestId);

                if ($approvalRequest->status !== 'pending') {
                    throw new \Exception('Request is not in pending status');
                }

                // Approve the request
                $approvalRequest->approve($adminId, $adminNotes);

                // Find the pending subscription for this user that requires approval
                $subscription = \App\Models\PlanSubscription::where('subscriber_id', $approvalRequest->user_id)
                    ->where('status', 'pending_approval')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($subscription) {
                    $plan = $subscription->plan;
                    $amount = $plan->price;

                    Log::info('Approving pending subscription', [
                        'subscription_id' => $subscription->id,
                        'plan_id' => $plan->id,
                        'is_free' => $amount == 0,
                    ]);

                    if ($amount == 0) {
                        // Free plan - activate immediately
                        $subscription->update(['status' => 'active']);

                        // Update enhancement
                        SubscriptionEnhancement::where('subscription_id', $subscription->id)
                            ->update(['status' => 'active']);
                    } else {
                        // Paid plan - move to pending so user can pay
                        $subscription->update(['status' => 'pending']);

                        // Update enhancement
                        SubscriptionEnhancement::where('subscription_id', $subscription->id)
                            ->update(['status' => 'payment_pending']);
                    }
                } else {
                    Log::warning('No pending_approval subscription found for approved request', [
                        'user_id' => $approvalRequest->user_id,
                        'request_id' => $requestId,
                    ]);
                }

                return $approvalRequest;
            });

            // Notify user of approval (OUTSIDE transaction to ensure it runs)
            try {
                $user = User::find($approvalRequest->user_id);
                if ($user) {
                    $user->notify(new StudentApprovalApproved($approvalRequest));
                    Log::info('Student approval approved notification sent', [
                        'user_id' => $user->id,
                        'request_id' => $approvalRequest->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to notify user of student approval', [
                    'error' => $e->getMessage(),
                    'request_id' => $approvalRequest->id,
                ]);
            }

            return $approvalRequest;
        } catch (\Exception $e) {
            Log::error('Student approval request approval failed', [
                'admin_id' => $adminId,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject student request
     */
    public function rejectRequest($requestId, int $adminId, string $rejectionReason, ?string $adminNotes = null): StudentApprovalRequest
    {
        try {
            $approvalRequest = DB::transaction(function () use ($requestId, $adminId, $rejectionReason, $adminNotes) {
                $approvalRequest = StudentApprovalRequest::findOrFail($requestId);

                if ($approvalRequest->status !== 'pending') {
                    throw new \Exception('Request is not in pending status');
                }

                // Reject the request
                $approvalRequest->reject($adminId, $rejectionReason, $adminNotes);

                // Find and cancel any pending_approval subscription
                \App\Models\PlanSubscription::where('subscriber_id', $approvalRequest->user_id)
                    ->where('status', 'pending_approval')
                    ->update(['status' => 'cancelled', 'canceled_at' => now()]);

                return $approvalRequest;
            });

            // Notify user of rejection (OUTSIDE transaction to ensure it runs)
            try {
                $user = User::find($approvalRequest->user_id);
                if ($user) {
                    $user->notify(new StudentApprovalRejected($approvalRequest, $rejectionReason));
                    Log::info('Student approval rejected notification sent', [
                        'user_id' => $user->id,
                        'request_id' => $approvalRequest->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to notify user of student rejection', [
                    'error' => $e->getMessage(),
                    'request_id' => $approvalRequest->id,
                ]);
            }

            return $approvalRequest;
        } catch (\Exception $e) {
            Log::error('Student approval request rejection failed', [
                'admin_id' => $adminId,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Overturn a rejection and approve the request.
     * This does NOT send automatic notifications - staff will contact user manually.
     */
    public function overturnRejection($requestId, int $adminId, ?string $adminNotes = null): StudentApprovalRequest
    {
        try {
            return DB::transaction(function () use ($requestId, $adminId, $adminNotes) {
                $approvalRequest = StudentApprovalRequest::findOrFail($requestId);

                if ($approvalRequest->status !== 'rejected') {
                    throw new \Exception('Request is not in rejected status');
                }

                // Approve the request (overturning the rejection)
                $approvalRequest->approve($adminId, $adminNotes . ' [Overturned from rejection]');

                Log::info('Student approval rejection overturned', [
                    'request_id' => $approvalRequest->id,
                    'admin_id' => $adminId,
                    'user_id' => $approvalRequest->user_id,
                ]);

                // Find any cancelled subscription and reactivate/set to pending
                $subscription = \App\Models\PlanSubscription::where('subscriber_id', $approvalRequest->user_id)
                    ->where('status', 'cancelled')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($subscription) {
                    $plan = $subscription->plan;
                    $amount = $plan->price;

                    if ($amount == 0) {
                        // Free plan - activate immediately
                        $subscription->update(['status' => 'active', 'canceled_at' => null]);
                        
                        SubscriptionEnhancement::where('subscription_id', $subscription->id)
                            ->update(['status' => 'active']);
                    } else {
                        // Paid plan - move to pending so user can pay
                        $subscription->update(['status' => 'pending', 'canceled_at' => null]);
                        
                        SubscriptionEnhancement::where('subscription_id', $subscription->id)
                            ->update(['status' => 'payment_pending']);
                    }

                    Log::info('Subscription reactivated after rejection overturn', [
                        'subscription_id' => $subscription->id,
                        'new_status' => $subscription->status,
                    ]);
                }

                return $approvalRequest;
            });
            // NO automatic notifications - staff will contact user manually
        } catch (\Exception $e) {
            Log::error('Student approval rejection overturn failed', [
                'admin_id' => $adminId,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if user can request student plan
     */
    public function canRequestStudentPlan(int $userId): array
    {
        try {
            // Check for existing pending/approved requests
            $existingRequest = StudentApprovalRequest::where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existingRequest) {
                return [
                    'can_request' => false,
                    'reason' => 'You already have an active student plan request',
                    'existing_request' => $existingRequest,
                ];
            }

            // Check for existing student subscription
            $user = \App\Models\User::find($userId);
            if ($user) {
                $subscription = $user->activeSubscription()
                    ->whereHas('plan', function ($q) {
                        $q->where('type', 'student');
                    })
                    ->first();

                if ($subscription) {
                    return [
                        'can_request' => false,
                        'reason' => 'You already have an active student subscription',
                    ];
                }
            }

            return [
                'can_request' => true,
                'reason' => 'You are eligible to request student plan',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check student plan eligibility', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
