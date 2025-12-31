<?php

namespace App\Services\Contracts;

use App\Models\PlanSubscription;
use App\Models\RenewalPreference;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionCoreServiceContract
{
    public function getCurrentSubscription(int $userId): ?array;

    public function getSubscriptionStatus(int $userId): array;

    public function getAvailablePlans(): Collection;

    public function getRenewalPreferences(int $userId): ?RenewalPreference;

    public function updateRenewalPreferences(int $userId, array $data): RenewalPreference;

    public function initiatePayment(int $userId, array $data): array;

    public function processMockPayment(int $userId, array $data): array;

    public function cancelSubscription(int $userId, ?string $reason = null): array;

    public function cancelSubscriptionPayment(int $subscriptionId, ?string $reason = null): array;
}
