<?php

namespace App\Services\Contracts;

use App\Models\RenewalPreference;
use Illuminate\Contracts\Auth\Authenticatable;
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

    /**
     * Get feature value for a user's subscription or system defaults
     */
    public function getFeatureValue(Authenticatable $user, string $featureSlug): mixed;
}
