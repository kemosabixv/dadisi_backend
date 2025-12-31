<?php

namespace App\Services\Contracts;

use Spatie\Permission\Models\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * PermissionServiceContract
 *
 * Contract for permission management
 */
interface PermissionServiceContract
{


    /**
     * Grant permission to role
     *
     * @param Authenticatable $actor
     * @param string $roleName
     * @param string $permissionName
     * @return bool
     */
    public function grantPermissionToRole(Authenticatable $actor, string $roleName, string $permissionName): bool;

    /**
     * Revoke permission from role
     *
     * @param Authenticatable $actor
     * @param string $roleName
     * @param string $permissionName
     * @return bool
     */
    public function revokePermissionFromRole(Authenticatable $actor, string $roleName, string $permissionName): bool;

    /**
     * List permissions
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function listPermissions(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get role permissions
     *
     * @param string $roleName
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRolePermissions(string $roleName): \Illuminate\Database\Eloquent\Collection;
}
