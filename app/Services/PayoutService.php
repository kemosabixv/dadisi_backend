<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Payout;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    protected $escrowService;

    public function __construct(EscrowService $escrowService)
    {
        $this->escrowService = $escrowService;
    }

    /**
     * Get the global commission rate from system settings.
     * Default to 3.00 if not found.
     */
    public function getCommissionRate(): float
    {
        $setting = SystemSetting::where('key', 'events.commission_rate')->first();
        return $setting ? (float) $setting->value : 3.00;
    }

    /**
     * Calculate payout details for an event.
     */
    public function calculatePayout(Event $event): array
    {
        $totalRevenue = $event->registrations()
            ->where('status', 'confirmed')
            ->sum('total_amount');

        $commissionRate = $this->getCommissionRate();
        $commissionAmount = ($totalRevenue * $commissionRate) / 100;
        $netAmount = $totalRevenue - $commissionAmount;

        return [
            'total_revenue' => $totalRevenue,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'currency' => $event->currency ?? 'KES',
        ];
    }

    /**
     * Create a payout request for an event.
     */
    public function createPayoutRequest(Event $event): Payout
    {
        if ($event->payouts()->whereIn('status', ['pending', 'processing', 'completed'])->exists()) {
            throw new \Exception("A payout request already exists for this event.");
        }

        $calc = $this->calculatePayout($event);
        $holdUntil = $this->escrowService->calculateHoldUntil($event);

        return Payout::create([
            'event_id' => $event->id,
            'organizer_id' => $event->organizer_id,
            'total_amount' => $calc['total_revenue'],
            'commission_amount' => $calc['commission_amount'],
            'payout_amount' => $calc['net_amount'],
            'currency' => $calc['currency'],
            'status' => 'pending',
            'hold_until' => $holdUntil,
            'reference' => 'PAY-' . strtoupper(bin2hex(random_bytes(4))),
        ]);
    }

    /**
     * Approve a payout.
     */
    public function approvePayout(Payout $payout, User $admin): bool
    {
        if ($payout->status !== 'pending') {
            throw new \Exception("Only pending payouts can be approved.");
        }

        return $payout->update([
            'status' => 'processing',
            'admin_notes' => ($payout->admin_notes ? $payout->admin_notes . "\n" : "") . "Approved by " . $admin->username . " on " . now()->toDateTimeString(),
        ]);
    }

    /**
     * Mark payout as completed.
     */
    public function completePayout(Payout $payout, ?string $reference = null): bool
    {
        return $payout->update([
            'status' => 'completed',
            'reference' => $reference ?? $payout->reference,
        ]);
    }
}
