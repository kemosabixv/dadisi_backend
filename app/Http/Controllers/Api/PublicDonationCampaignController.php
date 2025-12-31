<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DonationCampaignException;
use App\Exceptions\DonationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DonationCampaignResource;
use App\Services\Contracts\DonationCampaignServiceContract;
use App\Services\Contracts\DonationServiceContract;
use App\Http\Requests\Api\StorePublicDonationCampaignDonationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Donation Campaigns - Public
 */
class PublicDonationCampaignController extends Controller
{
    protected DonationCampaignServiceContract $campaignService;
    protected DonationServiceContract $donationService;

    public function __construct(
        DonationCampaignServiceContract $campaignService, 
        DonationServiceContract $donationService
    ) {
        $this->campaignService = $campaignService;
        $this->donationService = $donationService;
    }

    /**
     * List Active Campaigns
     * 
     * @group Donation Campaigns - Public
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['county_id']);
            $perPage = min((int) $request->input('per_page', 12), 50);

            $campaigns = $this->campaignService->listActiveCampaigns($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => DonationCampaignResource::collection($campaigns),
                'pagination' => [
                    'total' => $campaigns->total(),
                    'per_page' => $campaigns->perPage(),
                    'current_page' => $campaigns->currentPage(),
                    'last_page' => $campaigns->lastPage(),
                ],
                'message' => 'Active campaigns retrieved successfully'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationCampaignController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve campaigns'], 500);
        }
    }

    /**
     * Get Campaign Details
     * 
     * @group Donation Campaigns - Public
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $campaign = $this->campaignService->getCampaignBySlug($slug);

            if ($campaign->status !== 'active') {
                throw DonationCampaignException::notFound($slug);
            }

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($campaign)
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationCampaignController show failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve campaign details'], 500);
        }
    }

    /**
     * Donate to Campaign
     * 
     * @group Donation Campaigns - Public
     */
    public function donate(StorePublicDonationCampaignDonationRequest $request, string $slug): JsonResponse
    {
        try {
            $campaign = $this->campaignService->getCampaignBySlug($slug);
            $validated = $request->validated();

            // Validate campaign status and amount through service
            $this->campaignService->validateCampaignForDonation($campaign, (float) $validated['amount']);

            $user = $request->user('sanctum');
            
            $donationData = [
                'donor_name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'donor_email' => $validated['email'],
                'donor_phone' => $validated['phone_number'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? $campaign->currency,
                'county_id' => $validated['county_id'] ?? ($user?->profile?->county_id),
                'notes' => $validated['message'] ?? null,
                'campaign_id' => $campaign->id,
                'is_anonymous' => $validated['is_anonymous'] ?? false,
            ];

            $donation = $this->donationService->createDonation($user, $donationData);
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
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationCampaignController donate failed', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }
}
