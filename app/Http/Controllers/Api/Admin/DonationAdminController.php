<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DonationResource;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin - Donations
 *
 * Admin APIs for viewing and managing donations.
 */
class DonationAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List All Donations
     *
     * Returns paginated list of all donations with filters.
     *
     * @authenticated
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 25
     * @queryParam status string Filter by status. Example: paid
     * @queryParam campaign_id integer Filter by campaign. Example: 1
     * @queryParam search string Search donor name/email. Example: john
     * @queryParam start_date string Filter from date. Example: 2025-01-01
     * @queryParam end_date string Filter to date. Example: 2025-12-31
     *
     * @response 200 {
     *   "success": true,
     *   "data": [...],
     *   "pagination": {...}
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $query = Donation::with(['county', 'campaign', 'user']);

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Campaign filter
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->input('campaign_id'));
        }

        // Search by donor name or email
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('donor_name', 'like', "%{$search}%")
                  ->orWhere('donor_email', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $donations = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
     * Get Donation Details
     *
     * Returns detailed information about a specific donation.
     *
     * @authenticated
     *
     * @urlParam donation integer required Donation ID. Example: 1
     *
     * @response 200 {
     *   "success": true,
     *   "data": {...}
     * }
     */
    public function show(Donation $donation): JsonResponse
    {
        $donation->load(['county', 'campaign', 'user', 'payment']);

        return response()->json([
            'success' => true,
            'data' => new DonationResource($donation),
        ]);
    }

    /**
     * Get Dashboard Stats
     *
     * Returns donation statistics for admin dashboard.
     *
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "total_donations": 100,
     *     "total_amount": 500000,
     *     "paid_count": 80,
     *     "pending_count": 15,
     *     "failed_count": 5
     *   }
     * }
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_donations' => Donation::count(),
            'total_amount' => Donation::paid()->sum('amount'),
            'paid_count' => Donation::paid()->count(),
            'pending_count' => Donation::pending()->count(),
            'failed_count' => Donation::failed()->count(),
            'campaign_donations' => Donation::whereNotNull('campaign_id')->paid()->sum('amount'),
            'general_donations' => Donation::whereNull('campaign_id')->paid()->sum('amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
