<?php

namespace App\Services\Contracts;

use App\Models\Plan;
use Illuminate\Support\Collection;

interface PlanServiceContract
{
    public function listActivePlans(array $filters = []): Collection;

    public function getPlanDetails(Plan $plan): array;

    public function createPlan(array $data): Plan;

    public function updatePlan(Plan $plan, array $data): Plan;

    public function deletePlan(Plan $plan): bool;
}
