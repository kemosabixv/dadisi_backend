<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayoutResource;
use App\Models\Payout;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminPayoutController extends Controller
{
    protected $payoutService;

    public function __construct(PayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
        $this->middleware(['auth:sanctum', 'admin']);
    }

    /**
     * List all payouts
     * 
     * @group Admin Payouts
     */
    public function index(Request $request)
    {
        $query = Payout::with(['event', 'organizer']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return PayoutResource::collection($query->latest()->paginate());
    }

    /**
     * View payout details
     * 
     * @group Admin Payouts
     */
    public function show(Payout $payout)
    {
        return new PayoutResource($payout->load(['event', 'organizer']));
    }

    /**
     * Approve Payout
     * 
     * @group Admin Payouts
     */
    public function approve(Payout $payout)
    {
        try {
            $this->payoutService->approvePayout($payout, auth()->user());
            return response()->json(['message' => 'Payout approved and moved to processing.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Complete Payout
     * 
     * @group Admin Payouts
     */
    public function complete(Request $request, Payout $payout)
    {
        $validated = $request->validate([
            'reference' => 'nullable|string',
        ]);

        try {
            $this->payoutService->completePayout($payout, $validated['reference'] ?? null);
            return response()->json(['message' => 'Payout marked as completed.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Reject Payout
     * 
     * @group Admin Payouts
     */
    public function reject(Request $request, Payout $payout)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $payout->update([
            'status' => 'failed',
            'admin_notes' => ($payout->admin_notes ? $payout->admin_notes . "\n" : "") . "Rejected: " . $validated['reason'],
        ]);

        return response()->json(['message' => 'Payout rejected.']);
    }
}
