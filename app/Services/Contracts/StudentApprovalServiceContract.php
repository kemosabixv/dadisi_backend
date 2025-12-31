<?php

namespace App\Services\Contracts;

use App\Models\StudentApprovalRequest;
use Illuminate\Pagination\LengthAwarePaginator;

interface StudentApprovalServiceContract
{
    public function submitApprovalRequest(int $userId, array $data): StudentApprovalRequest;

    public function getApprovalStatus(int $userId): ?StudentApprovalRequest;

    public function getApprovalDetails($requestId): StudentApprovalRequest;

    public function listApprovalRequests(array $filters = []): LengthAwarePaginator;

    public function approveRequest($requestId, int $adminId, ?string $adminNotes = null): StudentApprovalRequest;

    public function rejectRequest($requestId, int $adminId, string $rejectionReason, ?string $adminNotes = null): StudentApprovalRequest;

    public function canRequestStudentPlan(int $userId): array;
}
