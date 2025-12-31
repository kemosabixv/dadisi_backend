<?php

namespace App\Services\Contracts;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * UserDataRetentionServiceContract
 *
 * Contract for user data retention policy management
 */
interface UserDataRetentionServiceContract
{
    /**
     * List all retention policies
     *
     * @param array $filters Optional filters (data_type, etc.)
     * @return array List of policies
     */
    public function listPolicies(array $filters = []): array;

    /**
     * Get specific policy details
     *
     * @param int $policyId Policy ID
     * @return array Policy details
     */
    public function getPolicyDetails(int $policyId): array;

    /**
     * Update a policy
     *
     * @param int $policyId Policy ID
     * @param array $data Updated data
     * @return array Updated policy
     */
    public function updatePolicy(int $policyId, array $data): array;

    /**
     * Get retention summary
     *
     * @return array Summary data
     */
    public function getSummary(): array;

    /**
     * Update retention days for a data type
     *
     * @param string $dataType Data type identifier
     * @param array $data Policy data
     * @return array Updated policy
     */
    public function updateRetentionDays(string $dataType, array $data): array;

    /**
     * Update scheduler configuration
     *
     * @param array $data Scheduler data
     * @return array Updated scheduler
     */
    public function updateScheduler(array $data): array;

    /**
     * List all configured schedulers
     *
     * @return array List of schedulers
     */
    public function listSchedulers(): array;

    /**
     * Archive user data to JSON file for backup/recovery
     *
     * @param User $user The user to archive
     * @return string Path to archived file
     */
    public function archiveUserData(User $user): string;

    /**
     * Export user data for GDPR data portability requests
     *
     * @param User $user The user whose data to export
     * @return array Complete user data export
     */
    public function exportUserData(User $user): array;

    /**
     * Anonymize user data when account is deleted
     *
     * Called when user requests deletion: anonymizes PII, preserves record
     * for audit. Scheduled cleanup job hard-deletes after retention period.
     *
     * @param User $user The user to anonymize
     * @return bool True on success
     */
    public function anonymizeUserData(User $user): bool;
}
