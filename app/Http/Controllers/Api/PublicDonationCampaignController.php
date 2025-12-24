<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DonationCampaignResource;
use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @group Donation Campaigns - Public
 *
 * Public APIs for viewing and donating to campaigns.
 */
class PublicDonationCampaignController extends Controller
{
    /**
     * List Active Campaigns
     *
     * Retrieves a paginated list of active donation campaigns.
     *
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Items per page (max 50, default 12). Example: 12
     * @queryParam county_id integer Filter campaigns by County ID. Matches `counties.id`. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Nairobi Biotech Hub Equipment Fund",
     *       "slug": "nairobi-biotech-hub-equipment-fund",
     *       "description": "Raising funds to purchase advanced DNA sequencing equipment...",
     *       "goal_amount": 5000000,
     *       "current_amount": 1250000,
     *       "currency": "KES",
     *       "starts_at": "2025-12-01T00:00:00Z",
     *       "ends_at": "2026-03-31T23:59:59Z",
     *       "status": "active",
     *       "county": {"id": 1, "name": "Nairobi"}
     *     }
     *   ],
     *   "pagination": {"total": 5, "per_page": 12, "current_page": 1, "last_page": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = DonationCampaign::with(['county', 'creator'])
            ->active()
            ->ongoing();

        // County filter
        if ($request->filled('county_id')) {
            $query->where('county_id', $request->input('county_id'));
        }

        $perPage = min((int) $request->input('per_page', 12), 50);
        $campaigns = $query->orderBy('published_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => DonationCampaignResource::collection($campaigns),
            'pagination' => [
                'total' => $campaigns->total(),
                'per_page' => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
            ],
        ]);
    }

    /**
     * Get Campaign Details
     *
     * Retrieves details of a specific campaign including progress.
     *
     * @urlParam campaign string required The Slug of the campaign. Example: nairobi-biotech-hub-equipment-fund
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "title": "Nairobi Biotech Hub Equipment Fund",
     *     "slug": "nairobi-biotech-hub-equipment-fund",
     *     "description": "Raising funds to purchase advanced DNA sequencing equipment...",
     *     "goal_amount": 5000000,
     *     "current_amount": 1250000,
     *     "currency": "KES",
     *     "starts_at": "2025-12-01T00:00:00Z",
     *     "ends_at": "2026-03-31T23:59:59Z",
     *     "status": "active",
     *     "county": {"id": 1, "name": "Nairobi"},
     *     "creator": {"id": 1, "username": "superadmin"}
     *   }
     * }
     */
    public function show(DonationCampaign $campaign): JsonResponse
    {
        // Only show active campaigns publicly
        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Campaign not found',
            ], 404);
        }

        $campaign->load(['county', 'creator']);

        return response()->json([
            'success' => true,
            'data' => new DonationCampaignResource($campaign),
        ]);
    }

    /**
     * Donate to Campaign
     *
     * Initiates a donation to a specific campaign.
     *
     * @urlParam campaign string required The Slug of the campaign to donate to. Example: nairobi-biotech-hub-equipment-fund
     *
     * @bodyParam amount number required Donation amount (must be >= campaign minimum). Example: 5000.00
     * @bodyParam currency string Currency code (KES or USD). Example: KES
     * @bodyParam first_name string required Legal first name. Example: John
     * @bodyParam last_name string required Legal last name. Example: Doe
     * @bodyParam email string required Valid email address for receipting. Example: john@example.com
     * @bodyParam phone_number string Optional phone number for M-Pesa. Example: +254700123456
     * @bodyParam message string optional Message to the organizers. Example: Supporting local innovation!
     * @bodyParam is_anonymous boolean hide name from public lists. Example: false
     * @bodyParam county_id integer Donor's home county ID. Example: 1
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Donation initiated",
     *   "data": {
     *     "donation_id": 123,
     *     "reference": "DON-NBI-7X9Z",
     *     "amount": 5000,
     *     "currency": "KES",
     *     "campaign": {"id": 1, "title": "Nairobi Biotech Hub Equipment Fund"},
     *     "redirect_url": "https://pay.pesapal.com/..."
     *   }
     * }
     */
    public function donate(Request $request, DonationCampaign $campaign): JsonResponse
    {
        // Check if campaign is active and ongoing
        if ($campaign->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This campaign is not accepting donations',
            ], 400);
        }

        // Check if campaign has ended
        if ($campaign->ends_at && $campaign->ends_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'This campaign has ended',
            ], 400);
        }

        // Check if campaign has started
        if ($campaign->starts_at && $campaign->starts_at->isFuture()) {
            return response()->json([
                'success' => false,
                'message' => 'This campaign has not started yet',
            ], 400);
        }

        // Validate request
        $rules = [
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['sometimes', 'string', 'in:KES,USD'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
            'is_anonymous' => ['nullable', 'boolean'],
            'county_id' => ['nullable', 'exists:counties,id'],
        ];

        // Add minimum amount validation if enforced
        $effectiveMinimum = $campaign->getEffectiveMinimumAmount();
        if ($effectiveMinimum !== null && $effectiveMinimum > 0) {
            $rules['amount'][] = 'min:' . $effectiveMinimum;
        }

        $validated = $request->validate($rules, [
            'amount.min' => $effectiveMinimum
                ? "Minimum donation for this campaign is {$campaign->currency} " . number_format($effectiveMinimum, 2)
                : 'Amount must be at least 1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $campaign, $request) {
                // Create donation record
                $userId = $request->user('sanctum')?->id;
                $countyId = $validated['county_id'] ?? null;

                // If no county provided, try to get from user profile
                if (!$countyId && $userId) {
                    $countyId = \App\Models\MemberProfile::where('user_id', $userId)->value('county_id');
                }

                $donation = Donation::create([
                    'user_id' => $userId,
                    'donor_name' => $validated['first_name'] . ' ' . $validated['last_name'],
                    'donor_email' => $validated['email'],
                    'donor_phone' => $validated['phone_number'] ?? null,
                    'county_id' => $countyId,
                    'amount' => $validated['amount'],
                    'currency' => $validated['currency'] ?? $campaign->currency,
                    'status' => 'pending',
                    'campaign_id' => $campaign->id,
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

                // TODO: Integrate with Pesapal to get redirect URL
                // For now, return the donation reference
                $redirectUrl = config('app.frontend_url') . '/donations/checkout/' . $donation->reference;

                return response()->json([
                    'success' => true,
                    'message' => 'Donation initiated',
                    'data' => [
                        'donation_id' => $donation->id,
                        'reference' => $donation->reference,
                        'amount' => $donation->amount,
                        'currency' => $donation->currency,
                        'campaign' => [
                            'id' => $campaign->id,
                            'title' => $campaign->title,
                            'slug' => $campaign->slug,
                        ],
                        'redirect_url' => $redirectUrl,
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create donation', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process donation. Please try again.',
            ], 500);
        }
    }
}
