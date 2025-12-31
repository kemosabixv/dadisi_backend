<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * ReconciliationServiceContract
 *
 * Contract for financial and data reconciliation
 */
interface ReconciliationServiceContract
{
    /**
     * Reconcile donations
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array Reconciliation result
     */
    public function reconcileDonations(Authenticatable $actor, array $filters = []): array;

    /**
     * Reconcile payments
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array Reconciliation result
     */
    public function reconcilePayments(Authenticatable $actor, array $filters = []): array;

    /**
     * Reconcile event registrations
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array Reconciliation result
     */
    public function reconcileEventRegistrations(Authenticatable $actor, array $filters = []): array;

    /**
     * Generate reconciliation report
     *
     * @param Authenticatable $actor
     * @param array $filters
     * @return array Report data
     */
    public function generateReconciliationReport(Authenticatable $actor, array $filters = []): array;

    /**
     * Get reconciliation status
     *
     * @param string $entity Entity type
     * @return array Status information
     */
    public function getReconciliationStatus(string $entity): array;

    /**
     * Flag discrepancy
     *
     * @param Authenticatable $actor
     * @param string $entity
     * @param int $entityId
     * @param string $description
     * @return bool
     */
    public function flagDiscrepancy(Authenticatable $actor, string $entity, int $entityId, string $description): bool;
}
