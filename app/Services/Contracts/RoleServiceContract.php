<?php

namespace App\Services\Contracts;

use App\DTOs\CreateRoleDTO;
use App\DTOs\UpdateRoleDTO;
use Spatie\Permission\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoleServiceContract
{
    public function listRoles(array $filters = []): LengthAwarePaginator;

    public function createRole(CreateRoleDTO $dto): Role;

    public function getRoleDetails(Role $role): array;

    public function updateRole(Role $role, UpdateRoleDTO $dto): Role;

    public function deleteRole(Role $role): bool;

    public function assignPermissionsToRole(Role $role, array $permissionNames): array;

    public function removePermissionsFromRole(Role $role, array $permissionNames): array;
}
