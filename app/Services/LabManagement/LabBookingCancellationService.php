<?php

namespace App\Services\LabManagement;

use App\Models\LabBooking;
use App\Models\User;
use App\Services\Contracts\RefundServiceContract;
use App\Services\SystemSettingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\LabException;
use App\Support\AdminAccessResolver;

class LabBookingCancellationService
{
    public function __construct(
        protected RefundServiceContract $refundService,
        protected SystemSettingService $settings
    ) {}

    /**
     * Cancel a lab booking
     *
     * @param LabBooking $booking
     * @param User|null $actor The user performing the cancellation
     * @param string|null $reason
     * @return bool
     * @throws LabException
     */
    public function cancelBooking(LabBooking $booking, ?User $actor = null, ?string $reason = null): bool
    {
        try {
            return DB::transaction(function () use ($booking, $actor, $reason) {
                // 1. Basic Validation
                if (!$booking->is_cancellable) {
                    throw LabException::updateFailed("This booking cannot be cancelled in its current state.");
                }

                // 2. Deadline Enforcement
                $isStaff = $actor && AdminAccessResolver::canAccessAdmin($actor);
                
                if (!$isStaff) {
                    $deadlineDays = (int) $this->settings->get('lab_booking_cancellation_deadline_days', 1);
                    $cancellationDeadline = $booking->starts_at->subDays($deadlineDays);

                    if (now()->isAfter($cancellationDeadline)) {
                        throw LabException::updateFailed(
                            "Cancellations are only allowed up to {$deadlineDays} day(s) before the booking starts."
                        );
                    }
                }

                $oldStatus = $booking->status;

                // 3. Update Status
                $booking->update([
                    'status' => LabBooking::STATUS_CANCELLED,
                    'rejection_reason' => $reason ?? 'Cancelled by user',
                ]);

                // 4. Handle Quota Restoration
                if ($booking->quota_consumed) {
                    // Logic to restore user quota would go here or in a QuotaService
                    // For now, we just mark it as not consumed anymore
                    $booking->update(['quota_consumed' => false]);
                }

                // 5. Handle Refunds (if paid)
                // Note: LabBooking doesn't currently have a direct payment link in the model,
                // but if we use a polymorphic payment system or link it to an order, we'd trigger it here.
                // For this implementation, we assume if it's approved and was paid for, we trigger a refund.
                // (This is a placeholder for actual payment integration)
                /*
                if ($booking->is_paid) {
                    $this->refundService->requestLabBookingRefund($booking, $reason);
                }
                */

                Log::info('Lab booking cancelled', [
                    'booking_id' => $booking->id,
                    'actor_id' => $actor?->id,
                    'reason' => $reason,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            if ($e instanceof LabException) {
                throw $e;
            }
            Log::error('Lab booking cancellation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            throw LabException::updateFailed($e->getMessage());
        }
    }
}
