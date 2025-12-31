<?php

namespace App\Services;

use App\Services\Contracts\StudentApprovalServiceContract;
use App\Models\StudentApprovalRequest;
use App\Models\Plan;
use App\Models\SubscriptionEnhancement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            return DB::transaction(function () use ($userId, $data) {
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
                    'submitted_at' => now(),
                    'expires_at' => now()->addDays(30),
                ]);

                Log::info('Student approval request submitted', [
                    'user_id' => $userId,
                    'request_id' => $approvalRequest->id,
                    'institution' => $data['student_institution'],
                ]);

                return $approvalRequest;
            });
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
            return StudentApprovalRequest::with('user')->findOrFail($requestId);
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
            $query = StudentApprovalRequest::with('user');

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            } else {
                $query->where('status', 'pending');
            }

            if (!empty($filters['county'])) {
                $query->where('county', $filters['county']);
            }

            return $query->orderBy('submitted_at', 'asc')
                ->paginate($filters['per_page'] ?? 15);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve approval requests', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Approve student request and create subscription
     */
    public function approveRequest($requestId, int $adminId, ?string $adminNotes = null): StudentApprovalRequest
    {
        try {
            return DB::transaction(function () use ($requestId, $adminId, $adminNotes) {
                $approvalRequest = StudentApprovalRequest::findOrFail($requestId);

                if ($approvalRequest->status !== 'pending') {
                    throw new \Exception('Request is not in pending status');
                }

                // Approve the request
                $approvalRequest->approve($adminId, $adminNotes);

                // Create student subscription
                $studentPlan = Plan::where('type', 'student')->first();
                if ($studentPlan) {
                    $subscription = $approvalRequest->user->activeSubscription()->create([
                        'plan_id' => $studentPlan->id,
                        'starts_at' => now(),
                        'ends_at' => now()->addYear(),
                        'status' => 'active',
                    ]);

                    // Create subscription enhancement
                    SubscriptionEnhancement::create([
                        'subscription_id' => $subscription->id,
                        'status' => 'active',
                        'max_renewal_attempts' => 3,
                        'metadata' => json_encode(['student_plan' => true]),
                    ]);
                }

                Log::info('Student approval request approved', [
                    'admin_id' => $adminId,
                    'request_id' => $requestId,
                    'user_id' => $approvalRequest->user_id,
                ]);

                return $approvalRequest;
            });
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
            return DB::transaction(function () use ($requestId, $adminId, $rejectionReason, $adminNotes) {
                $approvalRequest = StudentApprovalRequest::findOrFail($requestId);

                if ($approvalRequest->status !== 'pending') {
                    throw new \Exception('Request is not in pending status');
                }

                // Reject the request
                $approvalRequest->reject($adminId, $rejectionReason, $adminNotes);

                Log::info('Student approval request rejected', [
                    'admin_id' => $adminId,
                    'request_id' => $requestId,
                    'user_id' => $approvalRequest->user_id,
                    'reason' => $rejectionReason,
                ]);

                return $approvalRequest;
            });
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
