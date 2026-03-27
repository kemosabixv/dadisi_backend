<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingSeries;
use App\Services\Contracts\LabBookingServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingSeriesController extends Controller
{
    public function __construct(
        private LabBookingServiceContract $labBookingService
    ) {}

    /**
     * Cancel the entire booking series.
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $series = BookingSeries::with('bookings.payment')->findOrFail($id);

        // Security: Check if user owns the series
        if ($series->user_id !== auth()->id() && !auth()->user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $reason = $request->input('cancellation_reason', 'User cancelled');

        try {
            $result = $this->labBookingService->cancelSeries($series, $reason);
            
            return response()->json([
                'message' => 'Booking series cancelled successfully',
                'refund_initiated' => $result['refund_initiated'],
                'refund_status' => $result['refund'] ? $result['refund']->status : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to cancel booking series',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview refund for the entire series.
     */
    public function refundPreview(int $id): JsonResponse
    {
        $series = BookingSeries::findOrFail($id);

        if ($series->user_id !== auth()->id() && !auth()->user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $preview = $this->labBookingService->refundSeriesPreview($series);

        return response()->json($preview);
    }

    /**
     * Cancel the entire booking series (Administrative).
     */
    public function adminCancel(int $id, Request $request): JsonResponse
    {
        $series = BookingSeries::with('bookings.payment')->findOrFail($id);
        $user = auth()->user();

        // Security check: Lab Managers/Admins can cancel any series.
        // Supervisors can only cancel if assigned to the lab space.
        if ($user->hasRole('lab_supervisor')) {
            $representativeBooking = $series->bookings()->first();
            if ($representativeBooking && !$user->assignedLabSpaces()->where('lab_spaces.id', $representativeBooking->lab_space_id)->exists()) {
                return response()->json(['message' => 'Unauthorized: Supervisor not assigned to this lab.'], 403);
            }
        } elseif (!$user->hasRole('admin') && !$user->hasRole('lab_manager')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $reason = $request->input('reason');

        try {
            $result = $this->labBookingService->cancelSeries($series, $reason, $user);
            
            return response()->json([
                'success' => true,
                'message' => 'Booking series cancelled by staff',
                'refund_initiated' => $result['refund_initiated'],
                'refund_status' => $result['refund'] ? $result['refund']->status : null,
                'data' => [
                    'status' => $result['series']->status,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking series',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
