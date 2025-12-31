<?php

namespace App\Services\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Forum User Service Contract
 *
 * Defines the interface for forum user operations including listing members.
 */
interface ForumUserServiceContract
{
    /**
     * List active forum members with search, sort, and pagination
     *
     * @param array $filters Filter parameters (search, sort, per_page)
     * @return LengthAwarePaginator
     */
    public function listForumMembers(array $filters = []): LengthAwarePaginator;
}
