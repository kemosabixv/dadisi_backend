<?php

namespace App\Services\Users;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Contracts\UserBulkOperationServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * User Bulk Operation Service
 *
 * Handles bulk user operations including role assignments,
 * deletions, restorations, and updates with transaction support.
 *
 * @package App\Services\Users
 */
class UserBulkOperationService implements UserBulkOperationServiceContract
{
    /**
     * Bulk assign a role to multiple users
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 100)
     * @param string $role The role name to assign
     * @return int Number of users updated
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkAssignRole(Authenticatable $actor, array $userIds, string $role): int
    {
        // Validate limit
        if (count($userIds) > 100) {
            throw new \App\Exceptions\UserException("Cannot bulk assign role to more than 100 users", 422);
        }

        return DB::transaction(function () use ($actor, $userIds, $role) {
            try {
                $users = User::whereIn('id', $userIds)->get();
                $count = 0;

                foreach ($users as $user) {
                    if (!$user->hasRole($role)) {
                        $user->assignRole($role);
                        $count++;
                    }
                }

                // Log audit trail
                $this->logAudit($actor, "bulk_assign_role:{$role}", count($userIds));

                Log::info("Bulk assigned role {$role} to {$count} users by {$actor->getAuthIdentifier()}");

                return $count;
            } catch (\Exception $e) {
                Log::error("Failed to bulk assign role: {$e->getMessage()}");
                throw new \App\Exceptions\UserException("Failed to bulk assign role", 422, $e);
            }
        });
    }

    /**
     * Bulk remove a role from multiple users
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 100)
     * @param string $role The role name to remove
     * @return int Number of users updated
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkRemoveRole(Authenticatable $actor, array $userIds, string $role): int
    {
        // Validate limit
        if (count($userIds) > 100) {
            throw new \App\Exceptions\UserException("Cannot bulk remove role from more than 100 users", 422);
        }

        return DB::transaction(function () use ($actor, $userIds, $role) {
            try {
                $users = User::whereIn('id', $userIds)->get();
                $count = 0;

                foreach ($users as $user) {
                    if ($user->hasRole($role)) {
                        $user->removeRole($role);
                        $count++;
                    }
                }

                // Log audit trail
                $this->logAudit($actor, "bulk_remove_role:{$role}", count($userIds));

                Log::info("Bulk removed role {$role} from {$count} users by {$actor->getAuthIdentifier()}");

                return $count;
            } catch (\Exception $e) {
                Log::error("Failed to bulk remove role: {$e->getMessage()}");
                throw new \App\Exceptions\UserException("Failed to bulk remove role", 422, $e);
            }
        });
    }

    /**
     * Bulk delete users (soft delete)
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 50)
     * @return int Number of users deleted
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkDelete(Authenticatable $actor, array $userIds): int
    {
        // Validate limit
        if (count($userIds) > 50) {
            throw new \App\Exceptions\UserException("Cannot bulk delete more than 50 users", 422);
        }

        return DB::transaction(function () use ($actor, $userIds) {
            try {
                $count = User::whereIn('id', $userIds)->delete();

                // Log audit trail
                $this->logAudit($actor, 'bulk_delete_user', count($userIds));

                Log::info("Bulk deleted {$count} users by {$actor->getAuthIdentifier()}");

                return $count;
            } catch (\Exception $e) {
                Log::error("Failed to bulk delete users: {$e->getMessage()}");
                throw new \App\Exceptions\UserException("Failed to bulk delete users", 422, $e);
            }
        });
    }

    /**
     * Bulk restore deleted users
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 50)
     * @return int Number of users restored
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkRestore(Authenticatable $actor, array $userIds): int
    {
        // Validate limit
        if (count($userIds) > 50) {
            throw new \App\Exceptions\UserException("Cannot bulk restore more than 50 users", 422);
        }

        return DB::transaction(function () use ($actor, $userIds) {
            try {
                $count = User::withTrashed()
                    ->whereIn('id', $userIds)
                    ->onlyTrashed()
                    ->restore();

                // Log audit trail
                $this->logAudit($actor, 'bulk_restore_user', count($userIds));

                Log::info("Bulk restored {$count} users by {$actor->getAuthIdentifier()}");

                return $count;
            } catch (\Exception $e) {
                Log::error("Failed to bulk restore users: {$e->getMessage()}");
                throw new \App\Exceptions\UserException("Failed to bulk restore users", 422, $e);
            }
        });
    }

    /**
     * Bulk update user data
     *
     * @param Authenticatable $actor The user performing the action
     * @param array $userIds Array of user IDs (max 50)
     * @param array $data Data to update (username, email, etc.)
     * @return int Number of users updated
     *
     * @throws \App\Exceptions\UserException
     */
    public function bulkUpdate(Authenticatable $actor, array $userIds, array $data): int
    {
        // Validate limit
        if (count($userIds) > 50) {
            throw new \App\Exceptions\UserException("Cannot bulk update more than 50 users", 422);
        }

        return DB::transaction(function () use ($actor, $userIds, $data) {
            try {
                $count = User::whereIn('id', $userIds)->update($data);

                // Log audit trail
                $this->logAudit($actor, 'bulk_update_user', count($userIds));

                Log::info("Bulk updated {$count} users by {$actor->getAuthIdentifier()}");

                return $count;
            } catch (\Exception $e) {
                Log::error("Failed to bulk update users: {$e->getMessage()}");
                throw new \App\Exceptions\UserException("Failed to bulk update users", 422, $e);
            }
        });
    }

    /**
     * Log audit trail
     *
     * @param Authenticatable $actor The user performing the action
     * @param string $action The action performed
     * @param int $affectedCount Number of affected records
     * @return void
     */
    private function logAudit(Authenticatable $actor, string $action, int $affectedCount): void
    {
        try {
            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => "{$action}:count={$affectedCount}",
                'model_type' => User::class,
                'model_id' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to log audit: {$e->getMessage()}");
        }
    }
}
