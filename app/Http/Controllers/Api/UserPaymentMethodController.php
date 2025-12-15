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
     * List user's payment methods
     *
     * @group Payments
     * @authenticated
     * @response 200 {"data": []}
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $methods = UserPaymentMethod::where('user_id', $user->id)->get();

        return response()->json(['success' => true, 'data' => $methods]);
    }

    /**
     * Create a payment method (store a reference)
     *
     * @group Payments
     * @authenticated
     * @bodyParam type string required Payment method type (phone_pattern, card). Example: phone_pattern
     * @bodyParam identifier string optional Masked identifier or phone number. Example: 254712345678
     * @bodyParam label string optional Label for method. Example: Work phone
     * @response 201 {"success": true, "data": {}}
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
     * Update a payment method
     *
     * @group Payments
     * @authenticated
     * @bodyParam label string optional Label for method. Example: Personal card
     * @response 200 {"success": true, "data": {}}
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
     * Remove a payment method
     *
     * @group Payments
     * @authenticated
     * @response 200 {"success": true}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $method = UserPaymentMethod::where('user_id', $user->id)->where('id', $id)->firstOrFail();
        $method->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Set a payment method as primary
     *
     * @group Payments
     * @authenticated
     * @bodyParam id integer required Payment method id to set as primary
     * @response 200 {"success": true}
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
