<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserPaymentMethodRequest;
use App\Http\Requests\UpdateUserPaymentMethodRequest;
use App\Services\Contracts\UserPaymentMethodServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserPaymentMethodController extends Controller
{
    public function __construct(private UserPaymentMethodServiceContract $paymentMethodService)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List My Payment Methods
     *
     * Retrieves all payment methods associated with the authenticated user.
     * Returns a list of saved methods (e.g., MPESA numbers, tokenized cards) including their labels and primary status.
     *
     * @group Payment Methods
     * @groupDescription Endpoints for users to manage their saved payment methods (e.g., MPESA numbers, Cards). Note: Sensitive data is not stored directly.
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "type": "mobile_money",
     *       "identifier": "2547****5678",
     *       "label": "My MPESA",
     *       "is_primary": true,
     *       "created_at": "2025-12-01T10:00:00Z"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $methods = $this->paymentMethodService->list($request->user()->id);
            return response()->json(['success' => true, 'data' => $methods]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve payment methods', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve payment methods'], 500);
        }
    }

    /**
     * Add Payment Method
     *
     * Stores a new payment method reference for the user.
     * Note: This does not validate the payment method with the provider immediately, but saves the details (e.g., phone number pattern) for future transactions.
     * If marked as primary, it will replace any existing primary method.
     *
     * @group Payment Methods
     * @authenticated
    * @bodyParam type string required The type of payment method. Valid options: `phone_pattern`, `card`, `pesapal`. Example: phone_pattern
     * @bodyParam identifier string optional The masked identifier or phone number. Example: 254712345678
     * @bodyParam label string optional A user-friendly label for this method. Example: Work phone
     * @bodyParam is_primary boolean optional Set this as the default payment method. Example: true
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "id": 2,
     *     "type": "phone_pattern",
     *     "identifier": "254712345678",
     *     "label": "Work phone",
     *     "is_primary": true
     *   }
     * }     */
    public function store(StoreUserPaymentMethodRequest $request): JsonResponse
    {
        try {
            $method = $this->paymentMethodService->create($request->user()->id, $request->validated());
            return response()->json(['success' => true, 'data' => $method], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create payment method', ['error' => $e->getMessage(), 'user_id' => $request->user()->id]);
            return response()->json(['success' => false, 'message' => 'Failed to create payment method'], 500);
        }
    }

    /**
     * Update Payment Method
     *
     * Updates the details of an existing payment method, such as its label or primary status.
     *
     * @group Payment Methods
     * @authenticated
     * @bodyParam label string optional New label for the method. Example: Personal card
     * @bodyParam is_primary boolean optional Set as the primary method. Example: true
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "label": "Personal card",
     *     "is_primary": true
     *   }
     * }
     */
    public function update(UpdateUserPaymentMethodRequest $request, $id): JsonResponse
    {
        try {
            $method = $this->paymentMethodService->update($request->user()->id, $id, $request->validated());
            return response()->json(['success' => true, 'data' => $method]);
        } catch (\Exception $e) {
            Log::error('Failed to update payment method', ['error' => $e->getMessage(), 'user_id' => $request->user()->id, 'method_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to update payment method'], 500);
        }
    }

    /**
     * Remove Payment Method
     *
     * Deletes a saved payment method from the user's profile.
     *
     * @group Payment Methods
     * @authenticated
    * @response 200 {
    *   "success": true
    * }
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $this->paymentMethodService->delete($request->user()->id, $id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to delete payment method', ['error' => $e->getMessage(), 'user_id' => $request->user()->id, 'method_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete payment method'], 500);
        }
    }

    /**
     * Set Default Payment Method
     *
     * explicitly sets a specific payment method as the primary default for future transactions.
     * This will automatically unset 'is_primary' on all other methods belonging to the user.
     *
     * @group Payment Methods
     * @authenticated
     * @urlParam id integer required The ID of the payment method to set as primary. Example: 1
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "is_primary": true
     *   }
     * }
     */
    public function setPrimary(Request $request, $id): JsonResponse
    {
        try {
            $method = $this->paymentMethodService->setPrimary($request->user()->id, $id);
            return response()->json(['success' => true, 'data' => $method]);
        } catch (\Exception $e) {
            Log::error('Failed to set primary payment method', ['error' => $e->getMessage(), 'user_id' => $request->user()->id, 'method_id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to set primary payment method'], 500);
        }
    }
}
