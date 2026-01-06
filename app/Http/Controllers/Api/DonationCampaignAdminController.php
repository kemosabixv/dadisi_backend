<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DonationCampaignException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDonationCampaignRequest;
use App\Http\Requests\UpdateDonationCampaignRequest;
use App\Http\Resources\DonationCampaignResource;
use App\Models\DonationCampaign;
use App\Services\Contracts\DonationCampaignServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Donation Campaigns
 */
class DonationCampaignAdminController extends Controller
{
    protected DonationCampaignServiceContract $campaignService;

    public function __construct(DonationCampaignServiceContract $campaignService)
    {
        $this->campaignService = $campaignService;
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List All Campaigns
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'county_id']);
            $perPage = min((int) $request->input('per_page', 15), 100);

            $campaigns = $this->campaignService->listCampaigns($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => DonationCampaignResource::collection($campaigns),
                'pagination' => [
                    'total' => $campaigns->total(),
                    'per_page' => $campaigns->perPage(),
                    'current_page' => $campaigns->currentPage(),
                    'last_page' => $campaigns->lastPage(),
                ],
                'message' => 'Campaigns retrieved successfully'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve campaigns'], 500);
        }
    }

    /**
     * Get Campaign Creation Metadata
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function create(): JsonResponse
    {
        try {
            $counties = $this->campaignService->getCounties();

            return response()->json([
                'success' => true,
                'data' => [
                    'counties' => $counties,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController create failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve metadata'], 500);
        }
    }

    /**
     * Store New Campaign
     * 
     * Create a new donation campaign with optional hero image.
     *
     * @group Admin - Donation Campaigns
     * @authenticated
     * @responseFile status=201 storage/responses/donation-campaign-store.json
     */
    public function store(StoreDonationCampaignRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            if ($request->hasFile('hero_image')) {
                $path = $request->file('hero_image')->store('campaigns', 'public');
                $data['hero_image_path'] = $path;
            }

            $campaign = $this->campaignService->createCampaign($request->user(), $data);

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($campaign),
                'message' => 'Campaign created successfully'
            ], 201);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create campaign'], 500);
        }
    }

    /**
     * Show Campaign
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function show(string $campaign): JsonResponse
    {
        try {
            $campaign = $this->campaignService->getCampaignBySlug($campaign);

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($campaign)
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController show failed', ['error' => $e->getMessage(), 'campaign' => $campaign]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve campaign details'], 500);
        }
    }

    /**
     * Get Edit Data
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function edit(string $campaign): JsonResponse
    {
        try {
            $campaign = $this->campaignService->getCampaignBySlug($campaign);
            $counties = $this->campaignService->getCounties();

            return response()->json([
                'success' => true,
                'data' => [
                    'campaign' => new DonationCampaignResource($campaign),
                    'counties' => $counties,
                ]
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController edit failed', ['error' => $e->getMessage(), 'campaign' => $campaign]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve metadata'], 500);
        }
    }

    /**
     * Update Campaign
     * 
     * Update an existing donation campaign with optional hero image update.
     *
     * @group Admin - Donation Campaigns
     * @authenticated
     * @urlParam campaign integer required The campaign ID. Example: 1
     * @responseFile status=200 storage/responses/donation-campaign-update.json
     */
    public function update(UpdateDonationCampaignRequest $request, string $campaign): JsonResponse
    {
        try {
            $campaign = DonationCampaign::withTrashed()->where('slug', $campaign)->firstOrFail();
            $data = $request->validated();

            if ($request->hasFile('hero_image')) {
                $path = $request->file('hero_image')->store('campaigns', 'public');
                $data['hero_image_path'] = $path;
            }

            $updatedCampaign = $this->campaignService->updateCampaign($request->user(), $campaign, $data);

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($updatedCampaign),
                'message' => 'Campaign updated successfully'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController update failed', ['error' => $e->getMessage(), 'campaign' => $campaign]);
            return response()->json(['success' => false, 'message' => 'Failed to update campaign'], 500);
        }
    }

    /**
     * Delete Campaign
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function destroy(Request $request, string $campaign): JsonResponse
    {
        try {
            $campaign = DonationCampaign::where('slug', $campaign)->firstOrFail();
            $this->campaignService->deleteCampaign($request->user(), $campaign);

            return response()->json([
                'success' => true,
                'message' => 'Campaign moved to trash'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController destroy failed', ['error' => $e->getMessage(), 'campaign' => $campaign]);
            return response()->json(['success' => false, 'message' => 'Failed to delete campaign'], 500);
        }
    }

    /**
     * Restore Campaign
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function restore(Request $request, string $slug): JsonResponse
    {
        try {
            $campaign = $this->campaignService->restoreCampaign($request->user(), $slug);

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($campaign),
                'message' => 'Campaign restored successfully'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController restore failed', ['error' => $e->getMessage(), 'slug' => $slug]);
            return response()->json(['success' => false, 'message' => 'Failed to restore campaign'], 500);
        }
    }

    /**
     * Publish Campaign
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function publish(Request $request, string $campaign): JsonResponse
    {
        try {
            $campaign = DonationCampaign::where('slug', $campaign)->firstOrFail();
            $publishedCampaign = $this->campaignService->publishCampaign($request->user(), $campaign);

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($publishedCampaign),
                'message' => 'Campaign published successfully'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController publish failed', ['error' => $e->getMessage(), 'campaign' => $campaign]);
            return response()->json(['success' => false, 'message' => 'Failed to publish campaign'], 500);
        }
    }

    /**
     * Unpublish Campaign
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function unpublish(Request $request, string $campaign): JsonResponse
    {
        try {
            $campaign = DonationCampaign::where('slug', $campaign)->firstOrFail();
            $unpublishedCampaign = $this->campaignService->unpublishCampaign($request->user(), $campaign);

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($unpublishedCampaign),
                'message' => 'Campaign reverted to draft'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController unpublish failed', ['error' => $e->getMessage(), 'campaign' => $campaign]);
            return response()->json(['success' => false, 'message' => 'Failed to unpublish campaign'], 500);
        }
    }

    /**
     * Complete Campaign
     * 
     * @group Admin - Donation Campaigns
     * @authenticated
     */
    public function complete(Request $request, string $campaign): JsonResponse
    {
        try {
            $campaign = DonationCampaign::where('slug', $campaign)->firstOrFail();
            $completedCampaign = $this->campaignService->completeCampaign($request->user(), $campaign);

            return response()->json([
                'success' => true,
                'data' => new DonationCampaignResource($completedCampaign),
                'message' => 'Campaign marked as completed'
            ]);
        } catch (DonationCampaignException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationCampaignAdminController complete failed', ['error' => $e->getMessage(), 'campaign' => $campaign]);
            return response()->json(['success' => false, 'message' => 'Failed to complete campaign'], 500);
        }
    }
}
