<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRegistrationBenefitsController extends Controller
{
    public function __construct(
        protected EventQuotaService $quotaService
    ) {}

    /**
     * Get user-specific benefits for event registration.
     * Includes subscriber discount and priority access status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => true,
                'data' => [
                    'subscriber_discount_percent' => 0,
                    'has_priority_access' => false,
                    'is_subscriber' => false,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscriber_discount_percent' => $this->quotaService->getSubscriberDiscount($user),
                'has_priority_access' => $this->quotaService->hasPriorityAccess($user),
                'is_subscriber' => $user->hasActiveSubscription(),
            ]
        ]);
    }
}
