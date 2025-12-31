<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromoCodeResource;
use App\Models\Event;
use App\Models\PromoCode;
use App\Services\Contracts\PromoCodeServiceContract;
use App\Exceptions\PromoCodeException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PromoCodeController extends Controller
{
    public function __construct(private PromoCodeServiceContract $promoCodeService)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Validate Promo Code
     * 
     * @group Promo Codes
     */
    public function validateCode(ValidatePromoCodeRequest $request, Event $event): JsonResponse
    {
        try {
            $validated = $request->validated();
            $promo = $this->promoCodeService->redeemPromoCode($validated['code'], auth()->user());
            return response()->json(['success' => true, 'data' => new PromoCodeResource($promo)]);
        } catch (PromoCodeException $e) {
            Log::warning('Promo code validation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->statusCode ?? 404);
        } catch (\Exception $e) {
            Log::error('Failed to validate promo code', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Invalid or expired promo code.'], 404);
        }
    }

    /**
     * Admin: List all promo codes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', PromoCode::class);
            $filters = ['event_id' => $request->input('event_id')];
            $promoCodes = $this->promoCodeService->listPromoCodes($filters);
            return response()->json(['success' => true, 'data' => PromoCodeResource::collection($promoCodes)]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve promo codes', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve promo codes'], 500);
        }
    }

    /**
     * Admin: Create promo code
     */
    public function store(StorePromoCodeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PromoCode::class);
            $validated = $request->validated();
            $promo = $this->promoCodeService->createPromoCode(auth()->user(), $validated);
            return response()->json(['success' => true, 'data' => new PromoCodeResource($promo)], 201);
        } catch (PromoCodeException $e) {
            Log::error('Failed to create promo code', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->statusCode ?? 422);
        } catch (\Exception $e) {
            Log::error('Failed to create promo code', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create promo code'], 500);
        }
    }
}
