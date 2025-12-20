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
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 50). Example: 15
     *
     * @response 200 {
     *   "success": true,
     *   "data": [...],
     *   "pagination": {...}
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
     * @bodyParam amount number required Donation amount. Example: 1000.00
     * @bodyParam currency string Currency (KES or USD). Example: KES
     * @bodyParam first_name string required Donor first name. Example: John
     * @bodyParam last_name string required Donor last name. Example: Doe
     * @bodyParam email string required Donor email. Example: john@example.com
     * @bodyParam phone_number string Donor phone. Example: +254700123456
     * @bodyParam message string Optional message. Example: Keep up the good work!
     * @bodyParam county_id integer Donor's county. Example: 1
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Donation initiated",
     *   "data": {
     *     "donation_id": 1,
     *     "reference": "DON-ABC123XYZ",
     *     "redirect_url": "/donations/checkout/DON-ABC123XYZ"
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
     * @urlParam reference string required Donation reference. Example: DON-ABC123XYZ
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
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
