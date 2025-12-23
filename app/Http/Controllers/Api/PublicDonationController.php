<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DonationResource;
use App\Models\Donation;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Donations - Public
 *
 * APIs for creating and viewing donations.
 */
class PublicDonationController extends Controller
{
    public function __construct()
    {
        // Only index and store require auth, show by reference is public
        $this->middleware('auth:sanctum')->only(['index', 'store']);
    }

    /**
     * List User's Donations
     *
     * Returns paginated list of donations made by the authenticated user.
     *
     * @authenticated
     *
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Items per page (max 50, default 15). Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 123,
     *       "reference": "DON-NBI-7X9Z",
     *       "amount": 5000,
     *       "currency": "KES",
     *       "status": "completed",
     *       "created_at": "2025-12-20T14:30:00Z",
     *       "campaign": {"id": 1, "title": "Nairobi Biotech Hub Equipment Fund"}
     *     }
     *   ],
     *   "pagination": {"total": 1, "per_page": 15, "current_page": 1, "last_page": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min((int) $request->input('per_page', 15), 50);

        $donations = Donation::with(['county', 'campaign'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DonationResource::collection($donations),
            'pagination' => [
                'total' => $donations->total(),
                'per_page' => $donations->perPage(),
                'current_page' => $donations->currentPage(),
                'last_page' => $donations->lastPage(),
            ],
        ]);
    }

    /**
     * Create General Donation
     *
     * Creates a new donation not tied to any campaign.
     * Works for both authenticated and guest users.
     *
     * @bodyParam amount number required Donation amount. Example: 2500.00
     * @bodyParam currency string Currency code (KES or USD). Example: KES
     * @bodyParam first_name string required Legal first name. Example: John
     * @bodyParam last_name string required Legal last name. Example: Doe
     * @bodyParam email string required Valid email address. Example: john@example.com
     * @bodyParam phone_number string Optional phone number. Example: +254700123456
     * @bodyParam message string optional Personal message. Example: Go Dadisi!
     * @bodyParam county_id integer Donor's county ID. Example: 1
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Donation initiated",
     *   "data": {
     *     "donation_id": 456,
     *     "reference": "GEN-789-DEF",
     *     "amount": 2500,
     *     "currency": "KES",
     *     "redirect_url": "https://dadisilab.com/donations/checkout/GEN-789-DEF"
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'string', 'in:KES,USD'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
            'county_id' => ['nullable', 'exists:counties,id'],
        ]);

        try {
            return DB::transaction(function () use ($validated, $request) {
                $donation = Donation::create([
                    'user_id' => $request->user()?->id,
                    'donor_name' => $validated['first_name'] . ' ' . $validated['last_name'],
                    'donor_email' => $validated['email'],
                    'donor_phone' => $validated['phone_number'] ?? null,
                    'county_id' => $validated['county_id'] ?? null,
                    'amount' => $validated['amount'],
                    'currency' => $validated['currency'] ?? 'KES',
                    'status' => 'pending',
                    'notes' => $validated['message'] ?? null,
                ]);

                // Create pending payment record
                $payment = Payment::create([
                    'payable_type' => 'donation',
                    'payable_id' => $donation->id,
                    'gateway' => 'pesapal',
                    'method' => 'pending',
                    'status' => 'pending',
                    'amount' => $donation->amount,
                    'currency' => $donation->currency,
                    'order_reference' => $donation->reference,
                ]);

                $donation->update(['payment_id' => $payment->id]);

                $redirectUrl = config('app.frontend_url') . '/donations/checkout/' . $donation->reference;

                return response()->json([
                    'success' => true,
                    'message' => 'Donation initiated',
                    'data' => [
                        'donation_id' => $donation->id,
                        'reference' => $donation->reference,
                        'amount' => $donation->amount,
                        'currency' => $donation->currency,
                        'redirect_url' => $redirectUrl,
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create donation', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process donation. Please try again.',
            ], 500);
        }
    }

    /**
     * Get Donation by Reference
     *
     * Retrieves donation details by its reference code.
     * Used on the checkout page.
     *
     * @urlParam reference string required Unique donation reference code. Example: GEN-789-DEF
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 456,
     *     "reference": "GEN-789-DEF",
     *     "amount": 2500,
     *     "currency": "KES",
     *     "status": "pending",
     *     "donor_name": "John Doe",
     *     "created_at": "2025-12-22T10:00:00Z"
     *   }
     * }
     */
    public function show(string $reference): JsonResponse
    {
        $donation = Donation::with(['county', 'campaign'])
            ->where('reference', $reference)
            ->first();

        if (!$donation) {
            return response()->json([
                'success' => false,
                'message' => 'Donation not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new DonationResource($donation),
        ]);
    }
}
