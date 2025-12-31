<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\DonationException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DonationResource;
use App\Services\Contracts\DonationServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Admin - Donations
 */
class DonationAdminController extends Controller
{
    protected DonationServiceContract $donationService;

    public function __construct(DonationServiceContract $donationService)
    {
        $this->donationService = $donationService;
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List All Donations
     * 
     * @group Admin - Donations
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'campaign_id', 'search', 'start_date', 'end_date', 'county_id']);
            $perPage = min((int) $request->input('per_page', 25), 100);

            $donations = $this->donationService->listDonations($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => DonationResource::collection($donations),
                'pagination' => [
                    'total' => $donations->total(),
                    'per_page' => $donations->perPage(),
                    'current_page' => $donations->currentPage(),
                    'last_page' => $donations->lastPage(),
                ],
                'message' => 'Donations retrieved successfully'
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationAdminController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve donations'], 500);
        }
    }

    /**
     * Get Donation Details
     * 
     * @group Admin - Donations
     * @authenticated
     */
    public function show(string $id): JsonResponse
    {
        try {
            $donation = $this->donationService->getDonation($id);

            return response()->json([
                'success' => true,
                'data' => new DonationResource($donation),
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationAdminController show failed', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve donation details'], 500);
        }
    }

    /**
     * Get Dashboard Stats
     * 
     * @group Admin - Donations
     * @authenticated
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['county_id', 'campaign_id']);
            $stats = $this->donationService->getStatistics($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('DonationAdminController stats failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve statistics'], 500);
        }
    }

    /**
     * Export Donations Report
     * 
     * @group Admin - Donations
     * @authenticated
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
                'county_id' => 'nullable|integer|exists:counties,id',
                'campaign_id' => 'nullable|integer|exists:donation_campaigns,id',
                'format' => 'nullable|in:csv,json',
            ]);

            $format = $validated['format'] ?? 'json';
            $report = $this->donationService->generateReport($validated, $format);

            if ($format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => json_decode($report, true),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $report,
                    'filename' => 'donations-report-' . now()->format('Y-m-d') . '.csv'
                ]
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('DonationAdminController export failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to generate report'], 500);
        }
    }
}
