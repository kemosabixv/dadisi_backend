<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreUserPaymentMethodRequest;
use App\Http\Requests\UpdateUserPaymentMethodRequest;

class UserPaymentMethodController extends Controller
{
    public function __construct()
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
     *       "identifier": "2547****1234",
     *       "label": "My MPESA",
     *       "is_primary": true,
     *       "created_at": "2023-01-01T00:00:00Z"
     *     }
     *   ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $methods = UserPaymentMethod::where('user_id', $user->id)->get();

        return response()->json(['success' => true, 'data' => $methods]);
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
     * @bodyParam type string required The type of payment method. Valid options: `phone_pattern`, `card`. Example: phone_pattern
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
     * }
     */
    public function store(StoreUserPaymentMethodRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        $method = UserPaymentMethod::create(array_merge($validated, ['user_id' => $user->id]));

        // if caller requested primary, unset other primaries
        if (!empty($validated['is_primary'])) {
            UserPaymentMethod::where('user_id', $user->id)->where('id', '!=', $method->id)->update(['is_primary' => false]);
            $method->is_primary = true;
            $method->save();
        }

        return response()->json(['success' => true, 'data' => $method], 201);
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
        $user = $request->user();
        $method = UserPaymentMethod::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

        $method->update($validated);

        // if set primary, unset others
        if (array_key_exists('is_primary', $validated) && $validated['is_primary']) {
            UserPaymentMethod::where('user_id', $user->id)->where('id', '!=', $method->id)->update(['is_primary' => false]);
            $method->is_primary = true;
            $method->save();
        }

        return response()->json(['success' => true, 'data' => $method]);
    }

    /**
     * Remove Payment Method
     *
     * Deletes a saved payment method from the user's profile.
     *
     * @group Payment Methods
     * @authenticated
     * @response 200 {
     *   "success": true,
     *   "message": "Payment method deleted"
     * }
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $method = UserPaymentMethod::where('user_id', $user->id)->where('id', $id)->firstOrFail();
        $method->delete();

        return response()->json(['success' => true]);
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
        $user = $request->user();
        $method = UserPaymentMethod::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        // unset other primary methods
        UserPaymentMethod::where('user_id', $user->id)->update(['is_primary' => false]);

        $method->is_primary = true;
        $method->save();

        return response()->json(['success' => true, 'data' => $method]);
    }
}
