<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDonationCampaignRequest;
use App\Http\Requests\UpdateDonationCampaignRequest;
use App\Http\Resources\DonationCampaignResource;
use App\Models\AuditLog;
use App\Models\County;
use App\Models\DonationCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @group Donation Campaigns - Admin
 *
 * APIs for managing donation campaigns (staff only).
 */
class DonationCampaignAdminController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(DonationCampaign::class, 'campaign');
    }

    /**
     * List All Campaigns (Admin)
     *
     * Retrieves a paginated list of all donation campaigns with filters.
     *
     * @authenticated
     *
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     * @queryParam status string Filter by status (draft, active, completed, cancelled). Example: active
     * @queryParam search string Search by title. Example: Education
     * @queryParam county_id integer Filter by county. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": [...],
     *   "pagination": {"total": 10, "per_page": 15, "current_page": 1}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = DonationCampaign::with(['county', 'creator'])
            ->withCount('donations');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%");
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // County filter
        if ($request->filled('county_id')) {
            $query->where('county_id', $request->input('county_id'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $campaigns = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
     * Get Campaign Creation Metadata
     *
     * Retrieves metadata needed to create a new campaign (counties list).
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "counties": [{"id": 1, "name": "Nairobi"}]
     *   }
     * }
     */
    public function create(): JsonResponse
    {
        $counties = County::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'counties' => $counties,
            ],
        ]);
    }

    /**
     * Create New Campaign
     *
     * Creates a new donation campaign.
     *
     * @authenticated
     *
     * @bodyParam title string required Campaign title. Example: Education Fund 2025
     * @bodyParam description string required Rich text campaign description.
     * @bodyParam short_description string Summary for listings. Example: Help fund education.
     * @bodyParam goal_amount number Fundraising goal amount. Example: 100000.00
     * @bodyParam minimum_amount number Minimum donation amount. Example: 100.00
     * @bodyParam currency string required Currency code (KES or USD). Example: KES
     * @bodyParam hero_image file Campaign hero image.
     * @bodyParam county_id integer County ID. Example: 1
     * @bodyParam starts_at string Campaign start date. Example: 2025-01-01
     * @bodyParam ends_at string Campaign end date. Example: 2025-12-31
     * @bodyParam status string Status (draft or active). Example: draft
     *
     * @response 201 {
     *   "success": true,
     *   "message": "Campaign created successfully",
     *   "data": {...}
     * }
     */
    public function store(StoreDonationCampaignRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['created_by'] = $request->user()->id;

            // Handle hero image upload
            if ($request->hasFile('hero_image')) {
                $path = $request->file('hero_image')->store('campaigns', 'public');
                $data['hero_image_path'] = $path;
            }

            $campaign = DonationCampaign::create($data);
            $campaign->load(['county', 'creator']);

            // Audit log
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'created',
                'auditable_type' => DonationCampaign::class,
                'auditable_id' => $campaign->id,
                'new_values' => $campaign->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign created successfully',
                'data' => new DonationCampaignResource($campaign),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create campaign', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign',
            ], 500);
        }
    }

    /**
     * Get Campaign Details (Admin)
     *
     * Retrieves full details of a specific campaign.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
     * }
     */
    public function show(DonationCampaign $campaign): JsonResponse
    {
        $campaign->load(['county', 'creator']);

        return response()->json([
            'success' => true,
            'data' => new DonationCampaignResource($campaign),
        ]);
    }

    /**
     * Get Campaign Edit Data
     *
     * Retrieves campaign data along with counties for the edit form.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "campaign": {...},
     *     "counties": [...]
     *   }
     * }
     */
    public function edit(DonationCampaign $campaign): JsonResponse
    {
        $campaign->load(['county', 'creator']);
        $counties = County::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'campaign' => new DonationCampaignResource($campaign),
                'counties' => $counties,
            ],
        ]);
    }

    /**
     * Update Campaign
     *
     * Updates an existing donation campaign.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Campaign updated successfully",
     *   "data": {...}
     * }
     */
    public function update(UpdateDonationCampaignRequest $request, DonationCampaign $campaign): JsonResponse
    {
        try {
            $oldValues = $campaign->toArray();
            $data = $request->validated();

            // Handle hero image upload
            if ($request->hasFile('hero_image')) {
                // Delete old image
                if ($campaign->hero_image_path) {
                    Storage::disk('public')->delete($campaign->hero_image_path);
                }
                $path = $request->file('hero_image')->store('campaigns', 'public');
                $data['hero_image_path'] = $path;
            }

            $campaign->update($data);
            $campaign->load(['county', 'creator']);

            // Audit log
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'updated',
                'auditable_type' => DonationCampaign::class,
                'auditable_id' => $campaign->id,
                'old_values' => $oldValues,
                'new_values' => $campaign->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign updated successfully',
                'data' => new DonationCampaignResource($campaign),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update campaign', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update campaign',
            ], 500);
        }
    }

    /**
     * Delete Campaign (Soft)
     *
     * Soft deletes a campaign.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Campaign deleted successfully"
     * }
     */
    public function destroy(DonationCampaign $campaign): JsonResponse
    {
        try {
            $campaign->delete();

            // Audit log
            AuditLog::create([
                'user_id' => request()->user()->id,
                'action' => 'deleted',
                'auditable_type' => DonationCampaign::class,
                'auditable_id' => $campaign->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete campaign', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete campaign',
            ], 500);
        }
    }

    /**
     * Restore Deleted Campaign
     *
     * Restores a soft-deleted campaign.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Campaign restored successfully"
     * }
     */
    public function restore(string $slug): JsonResponse
    {
        try {
            $campaign = DonationCampaign::withTrashed()->where('slug', $slug)->firstOrFail();

            $this->authorize('restore', $campaign);

            $campaign->restore();
            $campaign->load(['county', 'creator']);

            // Audit log
            AuditLog::create([
                'user_id' => request()->user()->id,
                'action' => 'restored',
                'auditable_type' => DonationCampaign::class,
                'auditable_id' => $campaign->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign restored successfully',
                'data' => new DonationCampaignResource($campaign),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore campaign', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore campaign',
            ], 500);
        }
    }

    /**
     * Publish Campaign
     *
     * Sets campaign status to active and records published_at timestamp.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Campaign published successfully"
     * }
     */
    public function publish(DonationCampaign $campaign): JsonResponse
    {
        try {
            $this->authorize('publish', $campaign);

            $campaign->update([
                'status' => 'active',
                'published_at' => now(),
            ]);

            $campaign->load(['county', 'creator']);

            // Audit log
            AuditLog::create([
                'user_id' => request()->user()->id,
                'action' => 'published',
                'auditable_type' => DonationCampaign::class,
                'auditable_id' => $campaign->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign published successfully',
                'data' => new DonationCampaignResource($campaign),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish campaign', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish campaign',
            ], 500);
        }
    }

    /**
     * Unpublish Campaign
     *
     * Sets campaign status back to draft.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Campaign unpublished successfully"
     * }
     */
    public function unpublish(DonationCampaign $campaign): JsonResponse
    {
        try {
            $this->authorize('unpublish', $campaign);

            $campaign->update([
                'status' => 'draft',
            ]);

            $campaign->load(['county', 'creator']);

            // Audit log
            AuditLog::create([
                'user_id' => request()->user()->id,
                'action' => 'unpublished',
                'auditable_type' => DonationCampaign::class,
                'auditable_id' => $campaign->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign unpublished successfully',
                'data' => new DonationCampaignResource($campaign),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unpublish campaign', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to unpublish campaign',
            ], 500);
        }
    }

    /**
     * Complete Campaign
     *
     * Marks the campaign as completed.
     *
     * @authenticated
     *
     * @urlParam campaign string required Campaign slug. Example: education-fund-2025
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Campaign marked as completed"
     * }
     */
    public function complete(DonationCampaign $campaign): JsonResponse
    {
        try {
            $this->authorize('complete', $campaign);

            $campaign->update([
                'status' => 'completed',
            ]);

            $campaign->load(['county', 'creator']);

            // Audit log
            AuditLog::create([
                'user_id' => request()->user()->id,
                'action' => 'completed',
                'auditable_type' => DonationCampaign::class,
                'auditable_id' => $campaign->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign marked as completed',
                'data' => new DonationCampaignResource($campaign),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to complete campaign', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete campaign',
            ], 500);
        }
    }
}
