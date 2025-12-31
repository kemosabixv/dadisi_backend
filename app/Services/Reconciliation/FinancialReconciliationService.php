<?php

namespace App\Services\Reconciliation;

use App\Exceptions\ReconciliationException;
use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\Forum\Post;
use App\Services\Contracts\ReconciliationServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FinancialReconciliationService
 *
 * Handles financial and data reconciliation including
 * donations, payments, and event registrations.
 */
class FinancialReconciliationService implements ReconciliationServiceContract
{
    /**
     * Reconcile donations
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array
     *
     * @throws ReconciliationException
     */
    public function reconcileDonations(Authenticatable $actor, array $filters = []): array
    {
        try {
            $query = Donation::query();

            if (isset($filters['date_from']) && $filters['date_from']) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to']) && $filters['date_to']) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            if (isset($filters['county']) && $filters['county']) {
                $query->where('county', $filters['county']);
            }

            $totalCount = $query->count();
            $totalAmount = $query->sum('amount');
            $verifiedCount = (clone $query)->where('status', 'verified')->count();
            $verifiedAmount = (clone $query)->where('status', 'verified')->sum('amount');
            $unverifiedCount = (clone $query)->where('status', 'unverified')->count();
            $unverifiedAmount = (clone $query)->where('status', 'unverified')->sum('amount');

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'reconciled_donations',
                'model' => Donation::class,
                'model_id' => 0,
                'changes' => [
                    'total_count' => $totalCount,
                    'total_amount' => $totalAmount,
                ],
            ]);

            Log::info('Donations reconciliation completed', [
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'reconciled_by' => $actor->getAuthIdentifier(),
            ]);

            return [
                'entity' => 'donations',
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'verified_count' => $verifiedCount,
                'verified_amount' => $verifiedAmount,
                'unverified_count' => $unverifiedCount,
                'unverified_amount' => $unverifiedAmount,
                'discrepancy' => $unverifiedAmount > 0 ? true : false,
            ];
        } catch (\Exception $e) {
            throw ReconciliationException::donationReconciliationFailed($e->getMessage());
        }
    }

    /**
     * Reconcile payments
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array
     *
     * @throws ReconciliationException
     */
    public function reconcilePayments(Authenticatable $actor, array $filters = []): array
    {
        try {
            $query = Payment::query();

            if (isset($filters['date_from']) && $filters['date_from']) {
                $query->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to']) && $filters['date_to']) {
                $query->whereDate('created_at', '<=', $filters['date_to']);
            }

            $totalCount = $query->count();
            $totalAmount = $query->sum('amount');
            $completedCount = (clone $query)->where('status', 'completed')->count();
            $completedAmount = (clone $query)->where('status', 'completed')->sum('amount');
            $pendingCount = (clone $query)->where('status', 'pending')->count();
            $pendingAmount = (clone $query)->where('status', 'pending')->sum('amount');
            $failedCount = (clone $query)->where('status', 'failed')->count();

            AuditLog::create([
                'actor_id' => $actor->getAuthIdentifier(),
                'action' => 'reconciled_payments',
                'model' => Payment::class,
                'model_id' => 0,
                'changes' => [
                    'total_count' => $totalCount,
                    'total_amount' => $totalAmount,
                ],
            ]);

            Log::info('Payments reconciliation completed', [
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'reconciled_by' => $actor->getAuthIdentifier(),
            ]);

            return [
                'entity' => 'payments',
                'total_count' => $totalCount,
                'total_amount' => $totalAmount,
                'completed_count' => $completedCount,
                'completed_amount' => $completedAmount,
                'pending_count' => $pendingCount,
                'pending_amount' => $pendingAmount,
                'failed_count' => $failedCount,
                'discrepancy' => ($pendingCount > 0 || $failedCount > 0) ? true : false,
            ];
        } catch (\Exception $e) {
            throw ReconciliationException::paymentReconciliationFailed($e->getMessage());
        }
    }

    /**
     * Reconcile event registrations
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array
     *
     * @throws ReconciliationException
     */
    public function reconcileEventRegistrations(Authenticatable $actor, array $filters = []): array
    {
        try {
            // This would use EventRegistration model
            return [
                'entity' => 'event_registrations',
                'message' => 'Event registration reconciliation not yet implemented',
            ];
        } catch (\Exception $e) {
            throw ReconciliationException::eventReconciliationFailed($e->getMessage());
        }
    }

    /**
     * Generate reconciliation report
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array
     */
    public function generateReconciliationReport(Authenticatable $actor, array $filters = []): array
    {
        try {
            $donationReconciliation = $this->reconcileDonations($actor, $filters);
            $paymentReconciliation = $this->reconcilePayments($actor, $filters);

            return [
                'generated_at' => now(),
                'generated_by' => $actor->getAuthIdentifier(),
                'period_from' => $filters['date_from'] ?? null,
                'period_to' => $filters['date_to'] ?? null,
                'donations' => $donationReconciliation,
                'payments' => $paymentReconciliation,
                'has_discrepancies' => $donationReconciliation['discrepancy'] || $paymentReconciliation['discrepancy'],
            ];
        } catch (\Exception $e) {
            throw ReconciliationException::reportGenerationFailed($e->getMessage());
        }
    }

    /**
     * Get reconciliation status
     *
     * @param string $entity
     * @return array
     */
    public function getReconciliationStatus(string $entity): array
    {
        try {
            return match ($entity) {
                'donations' => [
                    'entity' => 'donations',
                    'last_reconciliation' => now()->subDay(),
                    'status' => 'completed',
                ],
                'payments' => [
                    'entity' => 'payments',
                    'last_reconciliation' => now()->subDay(),
                    'status' => 'completed',
                ],
                default => throw new \Exception("Unknown entity: {$entity}"),
            };
        } catch (\Exception $e) {
            Log::error('Failed to get reconciliation status', [
                'entity' => $entity,
                'error' => $e->getMessage(),
            ]);

            return [
                'entity' => $entity,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Flag discrepancy
     *
     * @param Authenticatable $actor
     * @param string $entity
     * @param int $entityId
     * @param string $description
     * @return bool
     *
     * @throws ReconciliationException
     */
    public function flagDiscrepancy(Authenticatable $actor, string $entity, int $entityId, string $description): bool
    {
        try {
            return DB::transaction(function () use ($actor, $entity, $entityId, $description) {
                AuditLog::create([
                    'actor_id' => $actor->getAuthIdentifier(),
                    'action' => 'flagged_discrepancy',
                    'model' => $entity,
                    'model_id' => $entityId,
                    'changes' => ['description' => $description],
                ]);

                Log::warning('Discrepancy flagged', [
                    'entity' => $entity,
                    'entity_id' => $entityId,
                    'description' => $description,
                    'flagged_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw ReconciliationException::flaggingFailed($e->getMessage());
        }
    }
}
