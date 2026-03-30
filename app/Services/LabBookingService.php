<?php

namespace App\Services;

use App\Models\BookingSeries;
use App\Models\LabBooking;
use App\Models\LabMaintenanceBlock;
use App\Models\LabSpace;
use App\Models\MaintenanceBlockRollover;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\BookingRescheduledNotification;
use App\Notifications\BookingRescheduleNeededNotification;
use App\Services\Contracts\LabBookingServiceContract;
use App\Services\Contracts\RefundServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabBookingService implements LabBookingServiceContract
{
    public function __construct(
        private OccupancyService $occupancyService,
        private RefundServiceContract $refundService
    ) {}

    /**
     * Get the user's current plan.
     * Checks both direct plan relationship and active subscription.
     */
    protected function getUserPlan(User $user): ?Plan
    {
        // First check direct plan relationship
        if ($user->plan) {
            return $user->plan;
        }

        // Fall back to active subscription's plan
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->whereNull('canceled_at')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->with('plan')
            ->first();

        return $subscription?->plan;
    }

    /**
     * Get the lab hours limit for a user's plan.
     *
     * @return float|null null for unlimited, 0 for no access, >0 for limit
     */
    protected function getLabHoursLimit(User $user): ?float
    {
        $plan = $this->getUserPlan($user);

        if (! $plan) {
            return 0; // No plan means no access
        }

        // Use SystemFeature-based approach
        $value = $plan->getFeatureValue('lab_hours_monthly', 0);

        // -1 represents unlimited
        if ($value === -1 || $value === '-1') {
            return null; // null = unlimited
        }

        return (float) $value;
    }

    /**
     * Check if user can book lab space based on subscription quota.
     */
    public function canBook(User $user, float $requestedHours): array
    {
        $plan = $this->getUserPlan($user);

        if (! $plan) {
            return [
                'allowed' => false,
                'reason' => 'no_subscription',
                'message' => 'You need an active subscription to book lab space.',
            ];
        }

        $monthlyLimit = $this->getLabHoursLimit($user);

        // Check if plan explicitly denies lab access (0 hours)
        if ($monthlyLimit === 0.0) {
            return [
                'allowed' => false,
                'reason' => 'plan_not_eligible',
                'message' => 'Lab space booking is not available on your current plan. Please upgrade.',
            ];
        }

        // Unlimited access
        if ($monthlyLimit === null) {
            return [
                'allowed' => true,
                'remaining_hours' => null,
                'unlimited' => true,
            ];
        }

        // Calculate used hours this month
        $usedHours = $this->getUsedHoursThisMonth($user);

        // 184h Global Cap Enforcement (PRD Section 11)
        $globalCap = 184.0;
        if (($usedHours + $requestedHours) > $globalCap) {
            return [
                'allowed' => false,
                'reason' => 'global_cap_exceeded',
                'message' => "The global monthly limit is {$globalCap}h. You have used {$usedHours}h this month.",
            ];
        }

        $remainingHours = max(0, $monthlyLimit - $usedHours);

        if ($requestedHours > $remainingHours) {
            return [
                'allowed' => false,
                'reason' => 'quota_exceeded',
                'message' => "You have {$remainingHours}h remaining this month. Requested: {$requestedHours}h.",
                'remaining_hours' => $remainingHours,
                'limit' => $monthlyLimit,
            ];
        }

        return [
            'allowed' => true,
            'remaining_hours' => $remainingHours - $requestedHours,
            'limit' => $monthlyLimit,
        ];
    }

    /**
     * Get user's quota status for display.
     */
    public function getQuotaStatus(User $user): array
    {
        $plan = $this->getUserPlan($user);

        if (! $plan) {
            return ['has_access' => false, 'reason' => 'no_subscription'];
        }

        $limit = $this->getLabHoursLimit($user);

        // 0 hours = no access
        if ($limit === 0.0) {
            return ['has_access' => false, 'reason' => 'plan_not_eligible'];
        }

        $usedHours = $this->getUsedHoursThisMonth($user);
        $isUnlimited = $limit === null;

        // Calculate next reset based on subscription anniversary
        $subscription = $user->activeSubscription()->first();
        $resetsAt = now()->endOfMonth(); // Default

        if ($subscription && $subscription->starts_at) {
            $day = $subscription->starts_at->day;
            $resetsAt = now()->day($day);
            if ($resetsAt->isPast()) {
                $resetsAt->addMonth();
            }
        }

        $percentageUsed = 0;
        if (! $isUnlimited && $limit > 0) {
            $percentageUsed = ($usedHours / $limit) * 100;
        }

        return [
            'has_access' => true,
            'plan_name' => $plan->display_name,
            'limit' => $isUnlimited ? null : $limit,
            'total' => $isUnlimited ? null : $limit,
            'unlimited' => $isUnlimited,
            'used' => (float) $usedHours,
            'remaining' => $isUnlimited ? null : max(0, $limit - $usedHours),
            'percentage_used' => round($percentageUsed, 2),
            'resets_at' => $resetsAt->toISOString(),
            'next_reset' => $resetsAt->toISOString(),
        ];
    }

    /**
     * Get used hours for the current month.
     */
    protected function getUsedHoursThisMonth(User $user): float
    {
        $subscription = $user->activeSubscription()->first();
        $query = $user->labBookings()
            ->whereIn('status', [LabBooking::STATUS_CONFIRMED, LabBooking::STATUS_COMPLETED])
            ->where('quota_consumed', true);

        if ($subscription && $subscription->starts_at) {
            $day = $subscription->starts_at->day;
            $currentReset = now()->day($day);
            if ($currentReset->isFuture()) {
                $currentReset->subMonth();
            }
            $query->where('starts_at', '>=', $currentReset);
        } else {
            $query->whereMonth('starts_at', now()->month)
                ->whereYear('starts_at', now()->year);
        }

        return (float) $query->get()->sum('duration_hours');
    }

    /**
     * Check if a time slot is available for a lab space, considering capacity.
     *
     * @param  LabSpace  $space
     * @param  int|null  $excludeBookingId  Exclude this booking when checking (for updates)
     */
    public function checkAvailability(int $spaceId, Carbon $start, Carbon $end, ?int $excludeBookingId = null): bool
    {
        $space = LabSpace::findOrFail($spaceId);

        // 1. Check if lab is active/available
        if (! $space->is_available) {
            return false;
        }

        // 2. Check Lab Closures (Holidays/Maintenance)
        $hasClosure = \App\Models\LabClosure::where(function ($q) use ($spaceId) {
            $q->where('lab_space_id', $spaceId)->orWhereNull('lab_space_id');
        })
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();

        if ($hasClosure) {
            return false;
        }

        // 3. Check Maintenance Blocks
        $hasMaintenance = \App\Models\LabMaintenanceBlock::where('lab_space_id', $spaceId)
            ->overlapping($start, $end)
            ->exists();

        if ($hasMaintenance) {
            return false;
        }

        // 3. Check opening hours
        $opensAt = $space->opens_at ?? $space->available_from;
        $closesAt = $space->closes_at ?? $space->available_until;

        if (! $opensAt || ! $closesAt) {
            // Cannot validate opening hours if both are missing
            return true;
        }

        $openTime = $opensAt->format('H:i');
        $closeTime = $closesAt->format('H:i');

        $requestedStart = $start->format('H:i');
        $requestedEnd = $end->format('H:i');

        if ($requestedStart < $openTime || $requestedEnd > $closeTime) {
            return false;
        }

        // 4. Capacity Check (Peak Occupancy) including Slot Holds
        $overlapping = LabBooking::where('lab_space_id', $spaceId)
            ->whereNotIn('status', [LabBooking::STATUS_CANCELLED, LabBooking::STATUS_REJECTED])
            ->overlapping($start, $end)
            ->when($excludeBookingId, function ($query, $id) {
                return $query->where('id', '!=', $id);
            })
            ->get();

        $overlappingHolds = \App\Models\SlotHold::where('lab_space_id', $spaceId)
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($start, $end) {
                $q->where('starts_at', '<', $end)
                    ->where('ends_at', '>', $start);
            })
            ->get();

        if ($overlapping->isEmpty() && $overlappingHolds->isEmpty()) {
            return true;
        }

        // Calculate peak occupancy within the range
        $points = $overlapping->pluck('starts_at')
            ->concat($overlapping->pluck('ends_at'))
            ->concat($overlappingHolds->pluck('starts_at'))
            ->concat($overlappingHolds->pluck('ends_at'))
            ->concat([$start, $end])
            ->filter(fn ($p) => $p->between($start, $end))
            ->unique()
            ->sort();

        foreach ($points as $point) {
            $checkPoint = $point->copy()->addSeconds(1);
            if ($checkPoint->gt($end)) {
                continue;
            }

            $occupancy = $overlapping->filter(function ($b) use ($checkPoint) {
                return $checkPoint->isBetween($b->starts_at, $b->ends_at, false); // exclusive bounds
            })->count();

            $holdOccupancy = $overlappingHolds->filter(function ($h) use ($checkPoint) {
                return $checkPoint->isBetween($h->starts_at, $h->ends_at, false); // exclusive bounds
            })->count();

            if (($occupancy + $holdOccupancy) >= $space->capacity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate booking price based on user subscription and lab rates.
     */
    /**
     * Initiate a booking by creating slot holds.
     * Stage 1 of Two-Phase Booking (Hold).
     */
    public function initiateBooking(?User $user, array $data): array
    {
        $spaceId = $data['lab_space_id'];
        $space = LabSpace::findOrFail($data['lab_space_id']);
        $slots = $data['slots']; // Array of ['starts_at', 'ends_at']
        $type = $data['type'] ?? 'single';
        $reference = 'HOLD-'.strtoupper(substr(uniqid(), -8));

        return DB::transaction(function () use ($user, $space, $slots, $type, $reference, $data) {
            // Validate user if provided
            if ($user && ! $user->id) {
                throw new \InvalidArgumentException('Invalid user provided for booking initiation.');
            }

            $holds = [];
            $totalHours = 0;

            $isRecurring = $type === 'recurring';
            $targetCount = $data['metadata']['target_count'] ?? count($slots);
            $processedSlots = [];
            $totalHours = 0;

            $currentDate = Carbon::parse($slots[0]['starts_at']);
            $startTime = $currentDate->format('H:i');
            $endTime = Carbon::parse($slots[0]['ends_at'])->format('H:i');
            $durationMinutes = Carbon::parse($slots[0]['starts_at'])->diffInMinutes(Carbon::parse($slots[0]['ends_at']));

            // Extract recurrence pattern if provided
            $daysOfWeek = $data['metadata']['days_of_week'] ?? null; // e.g., ['Mon', 'Wed']

            while (count($processedSlots) < $targetCount) {
                $start = $currentDate->copy()->setTimeFrom(Carbon::parse($startTime));
                $end = $start->copy()->addMinutes($durationMinutes);

                // Skip & Append Strategy (PRD Section 3.2)
                // 1. Check if the lab is open on this day
                // 2. Check if this day is in the recurrence pattern
                $isAllowedDay = true;
                if ($daysOfWeek) {
                    $dayName = $currentDate->format('D'); // Mon, Tue, etc.
                    if (! in_array($dayName, $daysOfWeek)) {
                        $isAllowedDay = false;
                    }
                }

                if ($isAllowedDay && $this->checkAvailability($space->id, $start, $end)) {
                    $processedSlots[] = ['starts_at' => $start, 'ends_at' => $end];
                    $totalHours += ($durationMinutes / 60);
                }

                // Move to next day (always addDay, the loop above will skip days not in pattern or unavailable)
                $currentDate->addDay();

                if (count($processedSlots) < $targetCount && $currentDate->diffInDays(Carbon::parse($slots[0]['starts_at'])) > 365) {
                    // Safety break if we can't find enough slots within a year
                    break;
                }

                if (count($processedSlots) > 100) {
                    break;
                } // Hard safety
            }

            $holds = [];
            foreach ($processedSlots as $slot) {
                $hold = \App\Models\SlotHold::create([
                    'reference' => $reference,
                    'lab_space_id' => $space->id,
                    'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'],
                    'expires_at' => now()->addMinutes(15),
                    'user_id' => $user?->id,
                    'guest_email' => $user ? null : $data['guest_email'] ?? null,
                ]);
                $holds[] = $hold;
            }

            // Create a pending series
            $series = \App\Models\BookingSeries::create([
                'user_id' => $user?->id,
                'lab_space_id' => $space->id,
                'reference' => $reference,
                'type' => $type,
                'total_hours' => $totalHours,
                'status' => 'pending',
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'guest_name' => $data['guest_name'] ?? null,
                    'guest_email' => $data['guest_email'] ?? null,
                    'purpose' => $data['purpose'] ?? null,
                    'title' => $data['title'] ?? null,
                ]),
            ]);

            // Link holds to series
            foreach ($holds as $hold) {
                $hold->update(['series_id' => $series->id]);
            }

            // Quota handling for yearly subscribers (PRD Section 3.2)
            $priceData = $this->calculateBookingPriceWithCommitments($user, $space, $processedSlots);

            return [
                'success' => true,
                'reference' => $reference,
                'expires_at' => now()->addMinutes(15)->toISOString(),
                'total_price' => $priceData['total_price'],
                'is_free_quota' => $priceData['is_free_quota'],
                'series_id' => $series->id,
                'breakdown' => $priceData['breakdown'] ?? [],
            ];
        });
    }

    /**
     * Calculate price considering yearly quota commitments.
     */
    public function calculateBookingPriceWithCommitments(?User $user, LabSpace $space, array $slots): array
    {
        if (! $user) {
            $totalHours = array_reduce($slots, fn ($sum, $s) => $sum + ($s['starts_at']->diffInMinutes($s['ends_at']) / 60), 0);

            return ['total_price' => $totalHours * ($space->hourly_rate ?? 0), 'is_free_quota' => false];
        }

        $plan = $this->getUserPlan($user);
        $quotaStatus = $this->getQuotaStatus($user);

        $totalPrice = 0;
        $isFreeQuota = false;
        $breakdown = [];

        // Group slots by month
        $months = [];
        foreach ($slots as $slot) {
            $month = $slot['starts_at']->copy()->startOfMonth()->toDateString();
            $hours = $slot['starts_at']->diffInMinutes($slot['ends_at']) / 60;
            $months[$month] = ($months[$month] ?? 0) + $hours;
        }

        foreach ($months as $month => $hours) {
            $monthDate = Carbon::parse($month);
            $isCurrentMonth = $monthDate->isCurrentMonth();

            if ($isCurrentMonth) {
                $currentRemaining = $quotaStatus['remaining'] ?? 0;
                $covered = min($currentRemaining, $hours);
                $billable = max(0, $hours - $covered);

                $totalPrice += $billable * ($space->hourly_rate ?? 0);
                if ($covered > 0) {
                    $isFreeQuota = true;
                }

                $breakdown[] = ['month' => $month, 'type' => 'quota', 'hours' => $covered, 'billable' => $billable];
            } else {
                // Future months
                // PRD Section 3.2: Split into "months within term" (commitment) and "post-term" (paid upfront)
                $subscription = $user->activeSubscription()->first() ?? $user->subscription()->first();
                $isWithinTerm = true;
                if ($subscription && $subscription->ends_at && $monthDate->gt($subscription->ends_at)) {
                    $isWithinTerm = false;
                }

                if ($plan && $plan->isYearly() && $isWithinTerm) {
                    // Commitment logic
                    $breakdown[] = ['month' => $month, 'type' => 'commitment', 'hours' => $hours, 'billable' => 0];
                    $isFreeQuota = true;
                } else {
                    // Post-term or monthly users pay upfront for future months
                    $billableAmount = $hours * ($space->hourly_rate ?? 0);
                    $totalPrice += $billableAmount;
                    $breakdown[] = ['month' => $month, 'type' => $isWithinTerm ? 'paid' : 'post_term', 'hours' => 0, 'billable' => $hours];
                }
            }
        }

        return [
            'total_price' => round($totalPrice, 2),
            'is_free_quota' => $isFreeQuota,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Confirm a booking by converting holds to formal bookings.
     * Stage 2 of Two-Phase Booking (Lock).
     */
    public function confirmBooking(string $reference, ?string $paymentId = null, ?string $paymentMethod = null): array
    {
        $series = \App\Models\BookingSeries::where('reference', $reference)->firstOrFail();
        $holds = \App\Models\SlotHold::where('reference', $reference)->get();

        if ($holds->isEmpty()) {
            throw new \Exception('No active holds found for this reference.');
        }

        return DB::transaction(function () use ($series, $holds, $paymentId, $paymentMethod) {
            $bookings = [];
            $quotaService = app(QuotaService::class);
            $totalHours = 0;

            // Calculate total hours for all holds
            foreach ($holds as $hold) {
                $totalHours += $hold->starts_at->diffInHours($hold->ends_at);
            }

            // PHASE 2: Validate quota for registered users before confirming
            if ($series->user_id) {
                // Validate quota availability for first month
                if (! $this->validateQuotaAvailability($series->user, $totalHours)['valid']) {
                    throw new \Exception('Insufficient monthly quota to confirm this booking.');
                }

                // Ensure quota commitment exists
                $quotaService->replenishMonthlyQuota($series->user);
            }

            foreach ($holds as $hold) {
                if ($hold->isExpired()) {
                    throw new \Exception('Booking failed: One or more slots have expired.');
                }

                // RE-VALIDATE: Ensure no maintenance or other conflict was created while hold was active
                if (! $this->checkAvailability($hold->lab_space_id, $hold->starts_at, $hold->ends_at)) {
                    throw new \Exception('One or more of your selected slots are no longer available due to a scheduled maintenance or conflict. Please select different times.');
                }

                $booking = LabBooking::create([
                    'lab_space_id' => $hold->lab_space_id,
                    'booking_series_id' => $series->id,
                    'user_id' => $hold->user_id,
                    'guest_name' => $series->metadata['guest_name'] ?? null,
                    'guest_email' => $series->metadata['guest_email'] ?? null,
                    'title' => $series->metadata['title'] ?? null,
                    'purpose' => $series->metadata['purpose'] ?? 'Lab Session',
                    'starts_at' => $hold->starts_at,
                    'ends_at' => $hold->ends_at,
                    'status' => LabBooking::STATUS_CONFIRMED,
                    'booking_reference' => $series->reference,
                    'payment_method' => $paymentMethod ?? 'quota',
                    'payment_id' => $paymentId,
                ]);

                // PHASE 2: Consume quota for registered users
                if ($booking->user_id) {
                    $this->consumeBookingQuota($booking);
                }

                $bookings[] = $booking;
                $hold->delete(); // Remove hold after successful booking
            }

            $series->update(['status' => 'confirmed']);

            // Create Audit Log
            \App\Models\BookingAuditLog::create([
                'series_id' => $series->id,
                'action' => 'confirmed',
                'user_id' => $series->user_id,
                'notes' => "Series confirmed via {$paymentMethod}. Total hours: {$totalHours}h. Quota consumed.",
            ]);

            return [
                'success' => true,
                'series_id' => $series->id,
                'bookings_count' => count($bookings),
                'hours_consumed' => $totalHours,
            ];
        });
    }

    /**
     * Heartbeat to renew a hold for another 15 minutes.
     */
    public function renewHold(string $reference): array
    {
        $holds = \App\Models\SlotHold::where('reference', $reference)->get();
        if ($holds->isEmpty()) {
            return ['success' => false, 'message' => 'Hold not found.'];
        }

        foreach ($holds as $hold) {
            if ($hold->isExpired()) {
                return ['success' => false, 'message' => 'Hold already expired.'];
            }
            $hold->update([
                'expires_at' => now()->addMinutes(15),
                'renewal_count' => $hold->renewal_count + 1,
            ]);
        }

        return [
            'success' => true,
            'expires_at' => now()->addMinutes(15)->toISOString(),
        ];
    }

    /**
     * Discovery for Flexible Bookings: Find and score optimal slots based on constraints.
     */
    public function discoverFlexibleSlots(int $spaceId, array $preferences, ?User $user = null): array
    {
        $space = LabSpace::findOrFail($spaceId);
        $startDate = Carbon::parse($preferences['start_date'] ?? now());
        $endDate = Carbon::parse($preferences['end_date'] ?? now()->addMonth());
        $targetHours = (float) $preferences['target_hours'];
        $maxDailyHours = (float) ($preferences['max_daily_hours'] ?? 8);
        $preferredDays = $preferences['preferred_days'] ?? null;
        $preferredStart = $preferences['preferred_start_time'] ?? null;
        $preferredEnd = $preferences['preferred_end_time'] ?? null;
        $consecutive = (bool) ($preferences['consecutive_days'] ?? false);

        $availableDays = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // Skip non-operating days
            if (! $this->isOperatingDay($space, $current)) {
                $current->addDay();

                continue;
            }

            if ($preferredDays && ! in_array($current->format('D'), $preferredDays)) {
                $current->addDay();

                continue;
            }

            $candidates = $this->getCandidateSlotsForDay($space, $current, $maxDailyHours, $preferredStart, $preferredEnd);
            if (! empty($candidates)) {
                $availableDays[] = [
                    'date' => $current->toDateString(),
                    'slots' => $candidates,
                ];
            }
            $current->addDay();
        }

        $combinations = $this->generateSlotCombinations($availableDays, $targetHours, $consecutive);

        $scored = array_map(function ($combo) {
            return [
                'slots' => $combo,
                'score' => $this->calculateComboScore($combo),
            ];
        }, $combinations);

        usort($scored, fn ($a, $b) => $a['score'] <=> $b['score']);

        return array_map(function ($opt) use ($space, $user) {
            $slots = array_map(function ($s) {
                return [
                    'starts_at' => Carbon::parse($s['starts_at']),
                    'ends_at' => Carbon::parse($s['ends_at']),
                    'hours' => $s['hours'],
                ];
            }, $opt['slots']);

            $priceData = $this->calculateBookingPriceWithCommitments($user, $space, $slots);

            return [
                'slots' => array_map(fn ($s) => [
                    'date' => $s['starts_at']->toDateString(),
                    'startHour' => (int) $s['starts_at']->format('H'),
                    'duration' => (int) $s['hours'],
                    'price' => $s['hours'] * ($space->hourly_rate ?? 0),
                ], $slots),
                'matchQuality' => max(0, 100 - $opt['score']),
                'totalPrice' => $priceData['total_price'],
                'is_free_quota' => $priceData['is_free_quota'],
                'breakdown' => $priceData['breakdown'] ?? [],
                'totalHours' => array_sum(array_column($slots, 'hours')),
            ];
        }, array_slice($scored, 0, 3));
    }

    /**
     * Discovery for Recurring Bookings: Generate occurrences with skip & append logic.
     */
    public function discoverRecurringSlots(int $spaceId, array $params, ?User $user = null): array
    {
        $space = LabSpace::findOrFail($spaceId);
        $targetCount = (int) ($params['target_count'] ?? 1);
        $daysOfWeek = $params['days_of_week'] ?? null;
        $startTime = $params['start_time']; // HH:mm
        $durationMinutes = (int) ($params['duration_minutes'] ?? 60);
        $currentDate = Carbon::parse($params['start_date']);

        // Validate time is possible at all for this lab
        $validation = $this->validateDiscoveryTime($space, $startTime, $durationMinutes);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
                'options' => []
            ];
        }

        $processedSlots = [];
        $skippedDates = [];
        $maxAttempts = 365; // Max 1 year scan
        $attempts = 0;

        while (count($processedSlots) < $targetCount && $attempts < $maxAttempts) {
            $attempts++;
            $start = $currentDate->copy()->setTimeFrom(Carbon::parse($startTime));
            $end = $start->copy()->addMinutes($durationMinutes);

            // Skip non-operating days without recording as skipped (unless explicitly requested)
            file_put_contents(storage_path('logs/recurring_debug.log'), "About to call discoverRecurringSlots\n", FILE_APPEND);
            if (! $this->isOperatingDay($space, $currentDate)) {
                $currentDate->addDay();

                continue;
            }

            $isAllowedDay = true;
            if ($daysOfWeek) {
                $dayName = $currentDate->format('D');
                if (! in_array($dayName, $daysOfWeek)) {
                    $isAllowedDay = false;
                }
            }

            $logMsg = sprintf("[%s] Day: %s, Allowed: %d, Processed: %d\n",
                $currentDate->toDateString(),
                $currentDate->format('D'),
                $isAllowedDay,
                count($processedSlots)
            );
            file_put_contents(storage_path('logs/recurring_debug.log'), $logMsg, FILE_APPEND);

            if ($isAllowedDay) {
                // Check if this specific day has a closure (holiday/maintenance)
                $isClosed = \App\Models\LabClosure::where(function ($q) use ($spaceId) {
                    $q->where('lab_space_id', $spaceId)->orWhereNull('lab_space_id');
                })
                    ->where('start_date', '<=', $currentDate->toDateString())
                    ->where('end_date', '>=', $currentDate->toDateString())
                    ->exists();

                if ($isClosed) {
                    $skippedDates[] = [
                        'date' => $currentDate->toDateString(),
                        'reason' => 'closure',
                    ];
                } elseif ($this->checkAvailability($space->id, $start, $end)) {
                    $processedSlots[] = [
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'status' => 'available',
                    ];
                } else {
                    $skippedDates[] = [
                        'date' => $currentDate->toDateString(),
                        'reason' => 'conflict',
                    ];
                }
            }

            $currentDate->addDay();
            if (count($processedSlots) > 100) {
                break;
            }
        }

        if ($attempts >= $maxAttempts && count($processedSlots) < $targetCount) {
            \Illuminate\Support\Facades\Log::warning("Recurring discovery reached max attempts ({$maxAttempts}) for Space ID {$spaceId}. Found ".count($processedSlots)."/{$targetCount} slots.");
        }

        $priceData = $this->calculateBookingPriceWithCommitments($user, $space, $processedSlots);

        return [
            'success' => true,
            'slots' => array_map(fn ($s) => [
                'date' => $s['starts_at']->toDateString(),
                'starts_at' => $s['starts_at']->toDateTimeString(),
                'ends_at' => $s['ends_at']->toDateTimeString(),
                'hours' => $durationMinutes / 60,
                'status' => $s['status'],
            ], $processedSlots),
            'skipped' => $skippedDates,
            'total_price' => $priceData['total_price'],
            'is_free_quota' => $priceData['is_free_quota'],
            'breakdown' => $priceData['breakdown'] ?? [],
            'total_hours' => count($processedSlots) * ($durationMinutes / 60),
        ];
    }

    protected function getCandidateSlotsForDay(LabSpace $space, Carbon $date, float $limit, ?string $preStart = null, ?string $preEnd = null): array
    {
        $open = Carbon::createFromFormat('H:i', $space->opens_at->format('H:i'))->setTimezone($date->timezone);
        $close = Carbon::createFromFormat('H:i', $space->closes_at->format('H:i'))->setTimezone($date->timezone);

        if ($preStart) {
            $pStart = Carbon::createFromFormat('H:i', $preStart);
            if ($pStart->gt($open)) {
                $open->setTimeFrom($pStart);
            }
        }
        if ($preEnd) {
            $pEnd = Carbon::createFromFormat('H:i', $preEnd);
            if ($pEnd->lt($close)) {
                $close->setTimeFrom($pEnd);
            }
        }

        $start = $date->copy()->setTimeFrom($open);
        $end = $date->copy()->setTimeFrom($close);

        // Find maximum available block on this day up to $limit
        $currentStart = $start->copy();
        $bestBlock = null;

        while ($currentStart->lt($end)) {
            $checkEnd = $currentStart->copy()->addHours($limit);
            if ($checkEnd->gt($end)) {
                $checkEnd = $end->copy();
            }

            if ($this->checkAvailability($space->id, $currentStart, $checkEnd)) {
                return [['starts_at' => $currentStart->toDateTimeString(), 'ends_at' => $checkEnd->toDateTimeString(), 'hours' => $currentStart->diffInMinutes($checkEnd) / 60]];
            }
            $currentStart->addHour();
        }

        return [];
    }

    protected function generateSlotCombinations(array $availableDays, float $targetHours, bool $consecutive): array
    {
        $allCombos = [];

        // Strategy: First try to find sets of days that meet target hours
        // If consecutive is requested, prioritize adjacent days

        if ($consecutive) {
            for ($i = 0; $i < count($availableDays); $i++) {
                $combo = [];
                $hours = 0;
                for ($j = $i; $j < count($availableDays); $j++) {
                    // Check if consecutive
                    if ($j > $i) {
                        $prevDate = Carbon::parse($availableDays[$j - 1]['date']);
                        $currDate = Carbon::parse($availableDays[$j]['date']);
                        if ($prevDate->diffInDays($currDate) > 1) {
                            break;
                        } // Not consecutive
                    }

                    foreach ($availableDays[$j]['slots'] as $slot) {
                        $combo[] = $slot;
                        $hours += $slot['hours'];
                        if ($hours >= $targetHours) {
                            $allCombos[] = $combo;
                            break 2;
                        }
                    }
                }
            }
        }

        // Always provide at least one non-consecutive best-fit if no consecutive found or if requested
        if (empty($allCombos)) {
            $combo = [];
            $hours = 0;
            foreach ($availableDays as $day) {
                foreach ($day['slots'] as $slot) {
                    $combo[] = $slot;
                    $hours += $slot['hours'];
                    if ($hours >= $targetHours) {
                        $allCombos[] = $combo;
                        break 2;
                    }
                }
            }
        }

        return ! empty($allCombos) ? array_slice($allCombos, 0, 5) : [];
    }

    /**
     * Calculate a quality score for a slot combination.
     * Lower score is better.
     */
    protected function calculateComboScore(array $slots): float
    {
        if (empty($slots)) {
            return 999999;
        }

        $dates = array_map(fn ($s) => Carbon::parse($s['starts_at'])->toDateString(), $slots);
        $uniqueDates = array_unique($dates);
        $count = count($uniqueDates);

        // 1. Fragmentation Penalty (100 points per day)
        $score = $count * 100;

        // 2. Span Penalty (10 points per day in range)
        $start = Carbon::parse(min($dates));
        $end = Carbon::parse(max($dates));
        $span = $start->diffInDays($end) + 1;
        $score += $span * 10;

        // 3. Start Delay Penalty (1 point per day from now/search start)
        // We use the first slot as the start delay anchor
        $score += now()->diffInDays($start);

        return $score;
    }

    public function calculateBookingPrice(User $user, LabSpace $space, float $hours): array
    {
        $isFreeQuota = false;
        $totalPrice = $hours * ($space->hourly_rate ?? 0);

        $quotaStatus = $this->getQuotaStatus($user);

        if ($quotaStatus['has_access']) {
            if ($quotaStatus['unlimited']) {
                $isFreeQuota = true;
                $totalPrice = 0;
            } else {
                $remainingQuota = $quotaStatus['remaining'];

                if ($remainingQuota >= $hours) {
                    $isFreeQuota = true;
                    $totalPrice = 0;
                } elseif ($remainingQuota > 0) {
                    // Partial quota application
                    $billableHours = $hours - $remainingQuota;
                    $totalPrice = $billableHours * ($space->hourly_rate ?? 0);
                    $isFreeQuota = true; // Still marked as quota-consuming
                }
            }
        }

        return [
            'total_price' => round($totalPrice, 2),
            'is_free_quota' => $isFreeQuota,
        ];
    }

    /**
     * Create a new booking.
     *
     * @throws \Exception
     */
    public function createBooking(User $user, array $data): array
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $durationHours = round($startsAt->diffInMinutes($endsAt) / 60, 2);

        $space = LabSpace::findOrFail($data['lab_space_id']);

        // 1. Initial Quota Check
        $quotaCheck = $this->canBook($user, $durationHours);
        if (! $quotaCheck['allowed']) {
            throw new \Exception($quotaCheck['message']);
        }

        // 2. Initial Availability Check (Pre-Transaction)
        $isAvailableInitially = $this->checkAvailability($space->id, $startsAt, $endsAt);

        return DB::transaction(function () use ($user, $space, $startsAt, $endsAt, $durationHours, $isAvailableInitially, $data) {
            // 3. Lock & Re-Check Availability (Post-Transaction/Race Detection)
            $isAvailableNow = $this->checkAvailability($space->id, $startsAt, $endsAt);

            if (! $isAvailableNow) {
                $isRaceCondition = $isAvailableInitially;

                return [
                    'success' => false,
                    'message' => $isRaceCondition
                        ? 'While you were booking, the last available spot for this slot was taken.'
                        : 'The selected slot is no longer available.',
                    'is_race_condition' => $isRaceCondition,
                ];
            }

            // 4. Price Calculation
            $priceData = $this->calculateBookingPrice($user, $space, $durationHours);
            $paymentRequired = $priceData['total_price'] > 0;

            // 5. Create Booking
            $booking = LabBooking::create([
                'lab_space_id' => $space->id,
                'user_id' => $user->id,
                'title' => $data['title'] ?? null,
                'purpose' => $data['purpose'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'slot_type' => $data['slot_type'] ?? LabBooking::SLOT_HOURLY,
                'recurrence_rule' => $data['recurrence_rule'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'status' => $paymentRequired ? LabBooking::STATUS_PENDING : LabBooking::STATUS_CONFIRMED,
                'quota_consumed' => $priceData['is_free_quota'],
                'total_price' => $priceData['total_price'],
                'receipt_number' => $paymentRequired ? null : LabBooking::generateReceiptNumber(),
            ]);

            $result = [
                'success' => true,
                'booking' => $booking->load('labSpace', 'user'),
                'payment_required' => $paymentRequired,
                'total_price' => $priceData['total_price'],
            ];

            // 6. Handle payment or send confirmation
            if ($paymentRequired) {
                $paymentResult = $this->initiatePayment($booking, $user);
                $result['redirect_url'] = $paymentResult['redirect_url'];
                $result['transaction_id'] = $paymentResult['transaction_id'];
                $result['message'] = 'Booking created. Complete payment to confirm.';
            } else {
                // Free booking — send confirmation immediately
                try {
                    $notificationService = app(\App\Services\Contracts\NotificationServiceContract::class);
                    $notificationService->sendLabBookingConfirmation($booking);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send lab booking confirmation', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $result['message'] = 'Booking confirmed successfully!';
            }

            return $result;
        });
    }

    /**
     * Create a guest booking (no user account required).
     * Guests always pay at the hourly rate.
     *
     * @param  array  $data  Must include guest_name, guest_email, lab_space_id, starts_at, ends_at, purpose
     *
     * @throws \Exception
     */
    public function createGuestBooking(array $data): array
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $durationHours = round($startsAt->diffInMinutes($endsAt) / 60, 2);

        $space = LabSpace::findOrFail($data['lab_space_id']);

        // Availability Check
        $isAvailableInitially = $this->checkAvailability($space->id, $startsAt, $endsAt);

        return DB::transaction(function () use ($space, $startsAt, $endsAt, $durationHours, $isAvailableInitially, $data) {
            $isAvailableNow = $this->checkAvailability($space->id, $startsAt, $endsAt);

            if (! $isAvailableNow) {
                $isRaceCondition = $isAvailableInitially;

                return [
                    'success' => false,
                    'message' => $isRaceCondition
                        ? 'While you were booking, the last available spot for this slot was taken.'
                        : 'The selected slot is no longer available.',
                    'is_race_condition' => $isRaceCondition,
                ];
            }

            // Guests always pay at hourly rate (no quota)
            $totalPrice = round($durationHours * ($space->hourly_rate ?? 0), 2);

            $booking = LabBooking::create([
                'lab_space_id' => $space->id,
                'user_id' => null,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'title' => $data['title'] ?? null,
                'purpose' => $data['purpose'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'slot_type' => $data['slot_type'] ?? LabBooking::SLOT_HOURLY,
                'status' => LabBooking::STATUS_PENDING,
                'total_price' => $totalPrice,
            ]);

            // Initiate payment for guest
            $paymentResult = $this->initiatePayment($booking);

            // Send initiated notification with payment link
            try {
                $notificationService = app(\App\Services\Contracts\NotificationServiceContract::class);
                $notificationService->sendLabBookingInitiated($booking, $paymentResult['redirect_url']);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send lab booking initiated notification', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'success' => true,
                'booking' => $booking->load('labSpace'),
                'payment_required' => true,
                'total_price' => $totalPrice,
                'redirect_url' => $paymentResult['redirect_url'],
                'transaction_id' => $paymentResult['transaction_id'],
                'message' => 'Booking created. Complete payment to confirm.',
            ];
        });
    }

    /**
     * Initiate a payment session for a lab booking.
     */
    protected function initiatePayment(LabBooking $booking, ?User $user = null): array
    {
        $paymentService = app(\App\Services\Payments\PaymentService::class);

        $paymentData = [
            'payable_type' => LabBooking::class,
            'payable_id' => $booking->id,
            'amount' => $booking->total_price,
            'currency' => 'KES',
            'description' => "Lab Booking: {$booking->labSpace->name} on {$booking->starts_at->format('M j, Y')}",
            'county' => $booking->labSpace->county ?? null,
        ];

        $result = $paymentService->processPayment($user, $paymentData);

        // Send initiated notification with payment link
        if ($user) {
            try {
                $notificationService = app(\App\Services\Contracts\NotificationServiceContract::class);
                $notificationService->sendLabBookingInitiated($booking, $result['redirect_url']);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send lab booking initiated notification', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Cancel a lab booking series.
     */
    public function cancelSeries(BookingSeries $series, string $reason = 'User cancelled', ?User $cancelledBy = null): array
    {
        return DB::transaction(function () use ($series, $reason, $cancelledBy) {
            $bookingsToCancel = $series->bookings()->where('status', '!=', LabBooking::STATUS_CANCELLED)->get();
            $representativeBooking = $bookingsToCancel->first() ?? $series->bookings()->first();

            if (! $representativeBooking) {
                throw new \Exception('Cannot cancel an empty booking series.');
            }

            foreach ($bookingsToCancel as $b) {
                // Future slots are released, past/attended are locked
                if ($b->starts_at->isFuture() && $b->status !== LabBooking::STATUS_COMPLETED) {
                    $wasQuota = $b->quota_consumed;

                    $b->update([
                        'status' => LabBooking::STATUS_CANCELLED,
                    ]);

                    // Release quota if it was consumed
                    if ($wasQuota) {
                        $this->releaseBookingQuota($b);
                    }
                }
            }

            $series->update(['status' => BookingSeries::STATUS_CANCELLED]);

            // Create Audit Log
            \App\Models\BookingAuditLog::create([
                'series_id' => $series->id,
                'booking_id' => $representativeBooking->id,
                'action' => 'cancelled',
                'user_id' => $cancelledBy ? $cancelledBy->id : $series->user_id,
                'notes' => $reason,
                'payload' => [
                    'admin_action' => $cancelledBy && ($cancelledBy->hasRole('admin') || $cancelledBy->hasRole('lab_manager') || $cancelledBy->hasRole('lab_supervisor')),
                ],
            ]);

            // PRD Section 10: Handle refunds based on payment type
            $refund = null;
            $payment = $representativeBooking->payment && $representativeBooking->payment->isPaid()
                ? $representativeBooking->payment
                : ($series->bookings()->whereHas('payment', fn ($q) => $q->where('status', 'paid'))->first()?->payment);

            if ($payment) {
                try {
                    // For series, we request total refund via the RefundService which is already series-aware
                    $refund = $this->refundService->requestLabBookingRefund($representativeBooking, 'Series Cancellation: '.$reason);

                    // Notify User and Staff about the refund request
                    if ($series->user) {
                        $series->user->notify(new \App\Notifications\LabBookingRefundSubmitted($representativeBooking, $refund));
                    }

                    // Notify Staff
                    $staff = \App\Models\User::role('super_admin')->get();
                    \Illuminate\Support\Facades\Notification::send($staff, new \App\Notifications\LabBookingRefundRequested($representativeBooking, $refund));

                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Refund request failed during series cancellation', [
                        'series_id' => $series->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Send cancellation notification to user
            if ($series->user) {
                if ($cancelledBy && $cancelledBy->id !== $series->user_id) {
                    $series->user->notify(new \App\Notifications\LabBookingCancelledByStaff($representativeBooking, $reason));
                } else {
                    $series->user->notify(new \App\Notifications\LabBookingCancelled($representativeBooking));
                }
            }

            return [
                'success' => true,
                'series' => $series->fresh(),
                'refund_initiated' => $refund !== null,
                'refund' => $refund,
            ];
        });
    }

    /**
     * Cancel a booking.
     * Refactored to use cancelSeries.
     */
    public function cancelBooking(LabBooking $booking, string $reason = 'User cancelled'): array
    {
        if ($booking->bookingSeries) {
            return $this->cancelSeries($booking->bookingSeries, $reason);
        }

        // Fallback for isolated bookings without a series (though rare in current architecture)
        return DB::transaction(function () use ($booking, $reason) {
            if ($booking->starts_at->isFuture() && $booking->status !== LabBooking::STATUS_COMPLETED) {
                $wasQuota = $booking->quota_consumed;
                $booking->update(['status' => LabBooking::STATUS_CANCELLED]);
                if ($wasQuota) {
                    $this->releaseBookingQuota($booking);
                }
            }

            // ... audit log, refund request etc (omitted for brevity as we prioritize series)
            // But let's keep it complete for robustness
            \App\Models\BookingAuditLog::create([
                'booking_id' => $booking->id,
                'action' => 'cancelled',
                'user_id' => $booking->user_id,
                'notes' => $reason,
            ]);

            $refund = null;
            if ($booking->payment && $booking->payment->isPaid()) {
                $refund = $this->refundService->requestLabBookingRefund($booking, 'Cancellation: '.$reason);
            }

            return [
                'success' => true,
                'booking' => $booking->fresh(),
                'refund_initiated' => $refund !== null,
                'refund' => $refund,
            ];
        });
    }

    /**
     * Get refund preview for a series.
     */
    public function refundSeriesPreview(BookingSeries $series): array
    {
        $representativeBooking = $series->bookings()->first();
        if (! $representativeBooking) {
            return [
                'refundable' => false,
                'amount' => 0,
                'reason' => 'Empty series',
            ];
        }

        return $this->refundPreview($representativeBooking);
    }

    /**
     * Get refund preview for a booking.
     */
    public function refundPreview(LabBooking $booking): array
    {
        if (! $booking->payment || ! $booking->payment->isPaid()) {
            // Check if any booking in the series has a payment
            $seriesPayment = $booking->bookingSeries?->bookings()->whereHas('payment', fn ($q) => $q->where('status', 'paid'))->first()?->payment;
            if (! $seriesPayment) {
                return [
                    'refundable' => false,
                    'amount' => 0,
                    'reason' => 'Booking is not paid',
                ];
            }
        }

        return $this->refundService->getLabBookingRefundPreview($booking);
    }

    /**
     * {@inheritDoc}
     */
    public function calculateRefund(LabBooking $booking): array
    {
        try {
            $preview = $this->refundPreview($booking);

            $originalAmount = (float) ($preview['original_amount'] ?? ($booking->total_price ?: 0));
            $refundAmount = (float) ($preview['amount'] ?? 0);

            return [
                'original_amount' => $originalAmount,
                'refund_amount' => $refundAmount,
                'deduction' => round($originalAmount - $refundAmount, 2),
                'reason' => $preview['explanation'] ?? ($preview['reason'] ?? ''),
                'is_eligible' => $preview['is_eligible'] ?? ($preview['refundable'] ?? false),
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Refund calculation failed', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'original_amount' => (float) ($booking->total_price ?: 0),
                'refund_amount' => 0,
                'deduction' => (float) ($booking->total_price ?: 0),
                'reason' => 'Error calculating refund: '.$e->getMessage(),
                'is_eligible' => false,
            ];
        }
    }

    /**
     * Generate a guest cancellation token.
     */
    public function generateGuestCancellationLink(LabBooking $booking): string
    {
        $token = hash_hmac('sha256', $booking->id.$booking->created_at, config('app.key'));

        return config('app.url')."/bookings/guest-cancel?id={$booking->id}&token={$token}";
    }

    /**
     * Check in a booking.
     * For users: Consolidates check-in for all bookings on the same day.
     */
    public function checkIn(LabBooking $booking, ?Carbon $checkInAt = null, ?User $staff = null): LabBooking
    {
        $checkInAt = $checkInAt ?? now();

        DB::transaction(function () use ($booking, $checkInAt, $staff) {
            $isStaff = $staff !== null;

            if ($booking->user_id) {
                // Daily consolidation for users
                $date = $booking->starts_at->toDateString();

                $bookings = LabBooking::where('user_id', $booking->user_id)
                    ->where('lab_space_id', $booking->lab_space_id)
                    ->whereDate('starts_at', $date)
                    ->whereNotIn('status', [LabBooking::STATUS_CANCELLED, LabBooking::STATUS_REJECTED])
                    ->get();

                foreach ($bookings as $b) {
                    // Update main booking record
                    $b->update(['checked_in_at' => $checkInAt]);

                    // Generate hourly slot logs for this booking, restricted to the starting day
                    $current = $b->starts_at->copy()->startOfHour();
                    while ($current < $b->ends_at && $current->isSameDay($b->starts_at)) {
                        \App\Models\AttendanceLog::updateOrCreate(
                            [
                                'booking_id' => $b->id,
                                'slot_start_time' => $current->toDateTimeString(),
                            ],
                            [
                                'lab_id' => $b->lab_space_id,
                                'user_id' => $b->user_id,
                                'status' => \App\Models\AttendanceLog::STATUS_ATTENDED,
                                'check_in_time' => $checkInAt,
                                'marked_by_id' => $staff?->id,
                                'notes' => $isStaff ? 'Check-in by staff (Daily consolidation)' : 'Self check-in',
                            ]
                        );
                        $current->addHour();
                    }
                }
            } else {
                // Individual check-in for guests
                $booking->update(['checked_in_at' => $checkInAt]);
                // Generate hourly slot logs for this booking, restricted to the starting day
                $current = $booking->starts_at->copy()->startOfHour();
                while ($current < $booking->ends_at && $current->isSameDay($booking->starts_at)) {
                    \App\Models\AttendanceLog::updateOrCreate(
                        [
                            'booking_id' => $booking->id,
                            'slot_start_time' => $current->toDateTimeString(),
                        ],
                        [
                            'lab_id' => $booking->lab_space_id,
                            'user_id' => $booking->user_id,
                            'status' => \App\Models\AttendanceLog::STATUS_ATTENDED,
                            'check_in_time' => $checkInAt,
                            'marked_by_id' => $staff?->id,
                            'notes' => $isStaff ? 'Check-in by staff' : 'Self check-in',
                        ]
                    );
                    $current->addHour();
                }
            }
        });

        return $booking->fresh();
    }

    /**
     * Check in via Lab Space static checkin_token.
     * Finds the user's active booking right now and checks them in.
     */
    public function checkInByToken(\App\Models\User $user, string $token): LabBooking
    {
        $labSpace = LabSpace::where('checkin_token', $token)->firstOrFail();

        // 15-minute grace period before starts_at for early check-in
        $booking = LabBooking::where('user_id', $user->id)
            ->where('lab_space_id', $labSpace->id)
            ->where('starts_at', '<=', now()->addMinutes(15))
            ->where('ends_at', '>=', now())
            ->where('status', LabBooking::STATUS_CONFIRMED)
            ->whereNull('checked_in_at')
            ->orderBy('starts_at', 'asc')
            ->first();

        if (! $booking) {
            throw new \Exception('No active booking found for you in this lab space right now.');
        }

        return $this->checkIn($booking);
    }

    /**
     * Undo check-in for a booking.
     * For users: Reverts all bookings on the same day.
     */
    public function undoCheckIn(LabBooking $booking): LabBooking
    {
        DB::transaction(function () use ($booking) {
            if ($booking->user_id) {
                // Daily consolidation for users
                $date = $booking->starts_at->toDateString();

                $bookings = LabBooking::where('user_id', $booking->user_id)
                    ->where('lab_space_id', $booking->lab_space_id)
                    ->whereDate('starts_at', $date)
                    ->get();

                foreach ($bookings as $b) {
                    $b->update([
                        'checked_in_at' => null,
                        'status' => LabBooking::STATUS_CONFIRMED,
                    ]);

                    // Update/Remove attendance log
                    \App\Models\AttendanceLog::where('booking_id', $b->id)->delete();
                }
            } else {
                // Individual for guests
                $booking->update([
                    'checked_in_at' => null,
                    'status' => LabBooking::STATUS_CONFIRMED,
                ]);

                \App\Models\AttendanceLog::where('booking_id', $booking->id)->delete();
            }
        });

        return $booking->fresh();
    }

    /**
     * Mark a booking as no-show.
     */
    public function markNoShow(LabBooking $booking): LabBooking
    {
        $booking->update([
            'status' => LabBooking::STATUS_NO_SHOW,
            // Quota is still consumed as a penalty
        ]);

        return $booking->fresh();
    }

    /**
     * Get availability calendar data for a lab space.
     */
    public function getAvailabilityCalendar(LabSpace $space, Carbon $start, Carbon $end): array
    {
        // Ensure end date includes the entire day
        $endDateTime = $end->copy()->endOfDay();

        // Get all bookings in range
        $bookings = LabBooking::where('lab_space_id', $space->id)
            ->where('starts_at', '<', $endDateTime)
            ->where('ends_at', '>', $start)
            ->whereNotIn('status', [LabBooking::STATUS_CANCELLED, LabBooking::STATUS_REJECTED])
            ->get();

        // Get all maintenance blocks in range
        $blocks = LabMaintenanceBlock::where('lab_space_id', $space->id)
            ->where('starts_at', '<', $endDateTime)
            ->where('ends_at', '>', $start)
            ->get();

        // Generate hourly slots for each day
        $events = [];
        $current = $start->copy()->startOfDay();
        $endDate = $end->copy()->endOfDay();

        while ($current <= $endDate) {
            $date = $current->format('Y-m-d');
            $events[$date] = [
                'hours' => $this->generateDaySlots($space, $current, $blocks, $bookings),
            ];
            $current->addDay();
        }

        return ['events' => $events];
    }

    /**
     * Generate hourly slots for a single day
     *
     * Returns occupancy information for each hour including available positions and capacity.
     * Uses flexible operating hours (opens_at/closes_at) and flexible capacity (slots_per_hour).
     */
    private function generateDaySlots(LabSpace $space, Carbon $date, $blocks, $bookings): array
    {
        $daySlots = [];

        // Get operating hours from space configuration
        // Defaults to 8 AM - 8 PM if not configured
        $opensHour = isset($space->opens_at)
            ? (int) Carbon::parse($space->opens_at)->format('H')
            : 8;

        $closesHour = isset($space->closes_at)
            ? (int) Carbon::parse($space->closes_at)->format('H')
            : 20;

        // Generate slots for each operating hour
        for ($hour = $opensHour; $hour < $closesHour; $hour++) {
            $hourStart = $date->copy()->setHour($hour)->setMinute(0)->setSecond(0);
            $hourEnd = $hourStart->copy()->addHour();

            // Check for maintenance/holiday/closure blocks
            $block = $blocks->first(fn ($b) => $b->starts_at <= $hourStart && $b->ends_at >= $hourEnd
            );

            if ($block) {
                $daySlots[] = [
                    'hour' => $hour,
                    'available' => false,
                    'booked' => 0,
                    'status' => 'full', // Blocked slots are considered "full"
                    // Occupancy for blocked slot
                    'occupancy' => [
                        'current' => 0,
                        'capacity' => max(1, (int) ($space->slots_per_hour ?? $space->capacity ?? 1)),
                        'available' => 0,
                        'percentage' => 100,
                        'is_full' => true,
                        'is_near_full' => true,
                    ],
                ];

                continue;
            }

            // Get occupancy data for this slot
            $occupancy = $this->occupancyService->getSlotOccupancy($space, $hourStart, $hourEnd);

            // Count overlapping bookings (legacy field for backward compatibility)
            $bookedCount = $bookings->filter(fn ($b) => $b->starts_at < $hourEnd && $b->ends_at > $hourStart
            )->count();

            // Determine status based on occupancy percentage
            $status = match (true) {
                $occupancy['percentage'] <= 50 => 'available',
                $occupancy['percentage'] < 100 => 'limited',
                default => 'full',
            };

            $daySlots[] = [
                'hour' => $hour,
                'available' => ! $occupancy['is_full'],
                'booked' => $bookedCount,  // Legacy field for backward compatibility
                'status' => $status,
                'occupancy' => $occupancy,  // New field with detailed occupancy info
            ];
        }

        return $daySlots;
    }

    /**
     * Find the earliest available slot for rescheduling
     * Searches from block end time forward, up to 180 days ahead
     */
    public function findAlternativeSlot(
        int $spaceId,
        int $durationHours,
        $blocksToAvoid, // LabMaintenanceBlock or Collection
        ?int $excludeBookingId = null
    ): ?array {
        $space = LabSpace::findOrFail($spaceId);
        $operatingStart = $space->opens_at ? \Carbon\Carbon::parse($space->opens_at)->hour : 8;
        $operatingEnd = $space->closes_at ? \Carbon\Carbon::parse($space->closes_at)->hour : 20;

        // Normalize blocks to avoid
        $blocksToAvoid = $blocksToAvoid instanceof \Illuminate\Support\Collection ? $blocksToAvoid : collect([$blocksToAvoid]);
        $primaryBlock = $blocksToAvoid->first();

        // Start searching from primary block end time (or first block in series)
        $searchStart = $primaryBlock->ends_at->copy();
        // For 180-day window from search start
        $searchEnd = $searchStart->copy()->addDays(179)->endOfDay();

        $currentTime = $searchStart->copy();

        while ($currentTime <= $searchEnd) {
            // Skip if current time is in the past
            if ($currentTime < now()) {
                $currentTime->addHour();

                continue;
            }

            // Skip if outside operating hours
            if ($currentTime->hour < $operatingStart || $currentTime->hour >= $operatingEnd) {
                // Jump to 8 AM of the same day or next day
                if ($currentTime->hour >= $operatingEnd) {
                    $currentTime->addDay()->setHour($operatingStart)->setMinute(0)->setSecond(0);
                } else {
                    $currentTime->setHour($operatingStart)->setMinute(0)->setSecond(0);
                }

                continue;
            }

            $candidateStart = $currentTime->copy();
            $candidateEnd = $candidateStart->copy()->addHours($durationHours);

            // Ensure end time is within operating hours
            if ($candidateEnd->hour > $operatingEnd ||
                ($candidateEnd->hour === $operatingEnd && $candidateEnd->minute > 0)) {
                // Skip to 8 AM next day
                $currentTime->addDay()->setHour($operatingStart)->setMinute(0)->setSecond(0);

                continue;
            }

            // Check if time slot has any blocks (maintenance/holiday/closure)
            // 1. Check against the explicit series passed in (avoiding new series members)
            $seriesConflict = $blocksToAvoid->contains(fn ($b) => 
                $b->starts_at < $candidateEnd && $b->ends_at > $candidateStart
            );

            if ($seriesConflict) {
               $currentTime->addHour();
               continue;
            }

            // 2. Check against OTHER existing blocks in DB
            $hasBlockConflict = LabMaintenanceBlock::where('lab_space_id', $spaceId)
                ->where('starts_at', '<', $candidateEnd)
                ->where('ends_at', '>', $candidateStart)
                ->whereNotIn('id', $blocksToAvoid->pluck('id')->filter()->toArray()) 
                ->exists();

            if ($hasBlockConflict) {
                $currentTime->addDay()->setHour($operatingStart)->setMinute(0)->setSecond(0);
                continue;
            }

            // Check if slot has conflicting bookings
            $hasBookingConflict = LabBooking::where('lab_space_id', $spaceId)
                ->when($excludeBookingId, fn ($q) => $q->whereNot('id', $excludeBookingId))
                ->where('starts_at', '<', $candidateEnd)
                ->where('ends_at', '>', $candidateStart)
                ->whereNotIn('status', [LabBooking::STATUS_CANCELLED, LabBooking::STATUS_REJECTED])
                ->exists();

            if (! $hasBookingConflict) {
                // Verify the slot is within the search window
                if ($candidateStart > $searchEnd) {
                    // Past search window, stop searching
                    break;
                }

                // Found available slot
                return [
                    'starts_at' => $candidateStart,
                    'ends_at' => $candidateEnd,
                ];
            }

            // Move to next hour
            $currentTime->addHour();
        }

        return null; // No available slot found within 6 months
    }

    /**
     * Roll over bookings that conflict with a maintenance block.
     * Moves confirmed bookings to the next available slot.
     */
    public function rollOverBookings(LabMaintenanceBlock $block): array
    {
        $conflicts = LabBooking::where('lab_space_id', $block->lab_space_id)
            ->where('starts_at', '<', $block->ends_at)
            ->where('ends_at', '>', $block->starts_at)
            ->where('status', LabBooking::STATUS_CONFIRMED)
            ->get();

        $results = [
            'total' => $conflicts->count(),
            'moved' => 0,
            'failed' => 0, // Failed auto-rollover, now 'pending_user'
            'details' => [],
        ];

        if ($conflicts->isEmpty()) {
            return $results;
        }

        foreach ($conflicts as $booking) {
            $durationHours = $booking->starts_at->diffInHours($booking->ends_at);
            if ($durationHours === 0) {
                $durationHours = 1;
            }

            // 1. Create Audit Log (Initiated)
            $rollover = MaintenanceBlockRollover::create([
                'maintenance_block_id' => $block->id,
                'original_booking_id' => $booking->id,
                'original_booking_data' => $booking->toArray(),
                'status' => 'initiated',
            ]);

            $newSlot = $this->findAlternativeSlot(
                $booking->lab_space_id,
                $durationHours,
                $block,
                $booking->id
            );

            if ($newSlot) {
                $oldStart = $booking->starts_at->copy();
                $oldEnd = $booking->ends_at->copy();

                $booking->update([
                    'starts_at' => $newSlot['starts_at'],
                    'ends_at' => $newSlot['ends_at'],
                    // Keep original status as it's still confirmed
                ]);

                // 2. Update Audit Log (Rolled Over)
                $rollover->update([
                    'status' => 'rolled_over',
                    'rolled_over_booking_id' => $booking->id,
                ]);

                $results['moved']++;
                $results['details'][] = [
                    'booking_id' => $booking->id,
                    'old_start' => $oldStart->toDateTimeString(),
                    'new_start' => $newSlot['starts_at']->toDateTimeString(),
                    'status' => 'moved',
                ];

                // Notify User (Tier 1: Informational)
                try {
                    $user = $booking->user;
                    if ($user) {
                        $user->notify(new BookingRescheduledNotification(
                            $booking,
                            $oldStart,
                            $oldEnd,
                            "Lab maintenance scheduled: {$block->title}",
                            $block
                        ));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to notify user for booking rollover: '.$e->getMessage());
                }
            } else {
                // 3. Auto-rollover failed -> Request user intervention
                $booking->update(['status' => 'pending_user_resolution']); // Suggested new status or handle via rollover pivot

                $rollover->update([
                    'status' => 'pending_user',
                ]);

                $results['failed']++;
                $results['details'][] = [
                    'booking_id' => $booking->id,
                    'status' => 'pending_user',
                    'reason' => 'No available slot found within rollover window',
                ];

                // Notify User (Tier 2: Action Required)
                try {
                    $user = $booking->user;
                    if ($user) {
                        $user->notify(new BookingRescheduleNeededNotification(
                            $booking,
                            $block
                        ));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to notify user for needed reschedule: '.$e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Validate quota availability for booking(s)
     *
     * PHASE 2: Quota Commitment Integration
     *
     * Checks if user has sufficient monthly quota to book requested hours
     * For recurring bookings spanning multiple months, validates quota for each month
     *
     * @param  float  $hours  Hours to book
     * @param  Carbon|null  $month  Month to validate for (default: current)
     * @return array ['valid' => bool, 'message' => string, 'remaining' => float]
     */
    public function validateQuotaAvailability(User $user, float $hours, ?Carbon $month = null): array
    {
        $quotaService = app(QuotaService::class);
        $month = ($month ?? now())->startOfMonth();

        // Ensure user has active subscription
        if (! $user->activeLabSubscription()) {
            return [
                'valid' => false,
                'message' => 'You must have an active lab subscription to book',
                'remaining' => 0,
            ];
        }

        // Get quota status for the month
        $status = $quotaService->getQuotaStatus($user, $month);

        if (! $status['commitment_exists']) {
            return [
                'valid' => false,
                'message' => 'No quota commitment found for this month',
                'remaining' => 0,
            ];
        }

        if ($status['remaining_hours'] < $hours) {
            return [
                'valid' => false,
                'message' => "Insufficient quota. You need {$hours} hours but only {$status['remaining_hours']} hours available.",
                'remaining' => $status['remaining_hours'],
            ];
        }

        return [
            'valid' => true,
            'message' => "Quota available. {$status['remaining_hours']} hours remaining after this booking.",
            'remaining' => $status['remaining_hours'] - $hours,
        ];
    }

    /**
     * Validate quota for recurring/multi-month bookings
     *
     * Checks if user has sufficient quota across ALL months needed for recurring booking
     *
     * @param  float  $hoursPerMonth  Hours per month for recurring booking
     * @param  Carbon  $startMonth  First month
     * @param  Carbon  $endMonth  Last month
     * @return array ['valid' => bool, 'message' => string, 'months_short' => array]
     */
    public function validateRecurringQuotaAvailability(User $user, float $hoursPerMonth, Carbon $startMonth, Carbon $endMonth): array
    {
        $quotaService = app(QuotaService::class);
        $monthsShort = [];
        $month = $startMonth->clone()->startOfMonth();

        while ($month <= $endMonth->clone()->startOfMonth()) {
            $status = $quotaService->getQuotaStatus($user, $month);

            if ($status['remaining_hours'] < $hoursPerMonth) {
                $monthsShort[] = [
                    'month' => $month->format('Y-m'),
                    'available' => $status['remaining_hours'],
                    'needed' => $hoursPerMonth,
                ];
            }

            $month->addMonth();
        }

        if (! empty($monthsShort)) {
            return [
                'valid' => false,
                'message' => 'Insufficient quota for recurring booking across multiple months',
                'months_short' => $monthsShort,
            ];
        }

        return [
            'valid' => true,
            'message' => 'Sufficient quota available for all months',
            'months_short' => [],
        ];
    }

    /**
     * Consume quota for a booking
     *
     * PHASE 2: Quota Commitment Integration
     *
     * Deducts hours from user's monthly quota commitment
     * Called when booking is confirmed (not at creation/hold stage)
     *
     * @return bool True if quota was consumed successfully
     */
    public function consumeBookingQuota(LabBooking $booking): bool
    {
        if (! $booking->user_id) {
            return true; // Guest bookings don't consume quota
        }

        $quotaService = app(QuotaService::class);
        $month = $booking->starts_at->startOfMonth();

        // Ensure quota commitment exists for this month
        $quotaService->replenishMonthlyQuota($booking->user);

        // Try to consume quota
        return $quotaService->commitQuotaForMonth(
            $booking->user,
            $month,
            (float) $booking->duration_hours
        );
    }

    /**
     * Release quota for a cancelled booking
     *
     * PHASE 2: Quota Commitment Integration
     *
     * @return bool True if quota was released
     */
    public function releaseBookingQuota(LabBooking $booking): bool
    {
        if (! $booking->user_id || ! $booking->user) {
            return true;
        }

        $quotaService = app(QuotaService::class);
        $month = $booking->starts_at->copy()->startOfMonth();
        $hours = (float) $booking->duration_hours;

        $success = $quotaService->restoreHours($booking->user, $month, $hours);

        if ($success) {
            $booking->update(['quota_consumed' => false]);
            event(new \App\Events\QuotaRestoredEvent($booking->user, $hours, $booking));

            // Notify User about quota restoration
            if ($booking->user) {
                $booking->user->notify(new \App\Notifications\LabBookingQuotaRestored($booking, $hours));
            }
        }

        return $success;
    }

    /**
     * Get quota status for user (PHASE 2 version using QuotaService)
     *
     * @param  Carbon|null  $month  Month to check (default: current)
     * @return array Quota status including remaining hours, warnings, etc.
     */
    public function getMonthlyQuotaStatus(User $user, ?Carbon $month = null): array
    {
        $quotaService = app(QuotaService::class);

        return $quotaService->getQuotaStatus($user, $month);
    }

    /**
     * Helper to check if a space is operating on a specific day.
     */
    protected function isOperatingDay(LabSpace $space, Carbon $date): bool
    {
        if (! $space->operating_days) {
            return true; // Default to all days if not set
        }

        $dayName = strtolower($date->format('l'));

        return in_array($dayName, array_map('strtolower', $space->operating_days));
    }

    /**
    /**
     * Validate that a given time window is possible at all for a space.
     * Returns a validation result [valid => bool, message => ?string].
     */
    protected function validateDiscoveryTime(LabSpace $space, string $startTime, int $durationMinutes): array
    {
        $openTime = Carbon::createFromFormat('H:i', $space->opens_at->format('H:i'));
        $closeTime = Carbon::createFromFormat('H:i', $space->closes_at->format('H:i'));

        $requestedStart = Carbon::createFromFormat('H:i', $startTime);
        $requestedEnd = $requestedStart->copy()->addMinutes($durationMinutes);

        if ($requestedStart->lt($openTime) || $requestedEnd->gt($closeTime)) {
            $msg = "The requested time ({$startTime} - ".$requestedEnd->format('H:i').') ';
            $msg .= "is outside the lab's operating hours (".$openTime->format('H:i').' - '.$closeTime->format('H:i').').';

            return [
                'valid' => false,
                'message' => $msg
            ];
        }

        return ['valid' => true, 'message' => null];
    }

    /**
     * Mark attendance for a specific slot.
     */
    public function markSlotAttendance(LabBooking $booking, Carbon $slotStartTime, string $status, ?User $staff = null): bool
    {
        return DB::transaction(function () use ($booking, $slotStartTime, $status, $staff) {
            $log = \App\Models\AttendanceLog::updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'slot_start_time' => $slotStartTime->copy()->startOfHour(),
                ],
                [
                    'lab_id' => $booking->lab_space_id,
                    'user_id' => $booking->user_id,
                    'status' => $status,
                    'check_in_time' => $booking->checked_in_at ?: now(),
                    'marked_by_id' => $staff?->id,
                    'notes' => 'Manual slot override by staff',
                ]
            );

            // If we're marking a slot as attended but the booking itself wasn't checked in,
            // we should probably trigger a standard check-in for the booking records.
            if ($status === \App\Models\AttendanceLog::STATUS_ATTENDED && ! $booking->checked_in_at) {
                $this->checkIn($booking, now(), $staff);
            }

            return $log !== null;
        });
    }

    /**
     * Resolve a booking conflict manually by selecting a new slot.
     */
    public function resolveConflict(LabBooking $booking, array $data): array
    {
        return DB::transaction(function () use ($booking, $data) {
            $newSpaceId = $data['lab_space_id'] ?? $booking->lab_space_id;
            $newStart = Carbon::parse($data['starts_at']);
            $newEnd = Carbon::parse($data['ends_at']);

            // 1. Basic availability check
            if (! $this->checkAvailability($newSpaceId, $newStart, $newEnd, $booking->id)) {
                return [
                    'success' => false,
                    'message' => 'The selected slot is no longer available.',
                ];
            }

            // 2. Update the booking
            $oldStart = $booking->starts_at->copy();
            $booking->update([
                'lab_space_id' => $newSpaceId,
                'starts_at' => $newStart,
                'ends_at' => $newEnd,
                'status' => LabBooking::STATUS_CONFIRMED, // Return to confirmed
            ]);

            // 3. Update the Audit Log
            $rollover = MaintenanceBlockRollover::where('original_booking_id', $booking->id)
                ->where('status', 'pending_user')
                ->latest()
                ->first();

            if ($rollover) {
                $rollover->update([
                    'status' => 'rolled_over',
                    'rolled_over_booking_id' => $booking->id,
                    'notes' => "Manually resolved by user. Moved from {$oldStart} to {$newStart}.",
                ]);
            }

            return [
                'success' => true,
                'message' => 'Booking rescheduled successfully.',
                'booking' => $booking,
            ];
        });
    }

    /**
     * Get attendance-specific analytics for a given date range.
     */
    public function getAttendanceAnalytics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $query = \App\Models\AttendanceLog::whereBetween('created_at', [$startDate, $endDate]);

        $totalLogs = (clone $query)->count();
        $attendedLogs = (clone $query)->where('status', \App\Models\AttendanceLog::STATUS_ATTENDED)->count();
        $noShowLogs = (clone $query)->where('status', \App\Models\AttendanceLog::STATUS_NO_SHOW)->count();

        $uniqueAttendees = (clone $query)->where('status', \App\Models\AttendanceLog::STATUS_ATTENDED)
            ->distinct('user_id')
            ->count('user_id');

        // Peak Hour logic (most attended slots)
        $peakHourData = (clone $query)->where('status', \App\Models\AttendanceLog::STATUS_ATTENDED)
            ->whereNotNull('slot_start_time')
            ->select(DB::raw('HOUR(slot_start_time) as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        $peakHour = $peakHourData ? sprintf('%02d:00', $peakHourData->hour) : 'N/A';

        return [
            'attendance_rate' => $totalLogs > 0 ? round(($attendedLogs / $totalLogs) * 100, 1) : 0,
            'no_show_rate' => $totalLogs > 0 ? round(($noShowLogs / $totalLogs) * 100, 1) : 0,
            'unique_attendees' => $uniqueAttendees,
            'peak_attendance_hour' => $peakHour,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Retry an auto-rollover for an escalated or pending user booking.
     */
    public function retryAutoRollover(MaintenanceBlockRollover $rollover): array
    {
        $booking = $rollover->originalBooking;
        $block = $rollover->maintenanceBlock;

        if (! $booking || ! $block) {
            return [
                'success' => false,
                'message' => 'Invalid rollover record: missing booking or block reference.',
            ];
        }

        $durationHours = $booking->starts_at->diffInHours($booking->ends_at) ?: 1;

        $newSlot = $this->findAlternativeSlot(
            $booking->lab_space_id,
            $durationHours,
            $block,
            $booking->id
        );

        if ($newSlot) {
            $oldStart = $booking->starts_at->copy();
            $oldEnd = $booking->ends_at->copy();

            $booking->update([
                'starts_at' => $newSlot['starts_at'],
                'ends_at' => $newSlot['ends_at'],
                'status' => LabBooking::STATUS_CONFIRMED,
            ]);

            $rollover->update([
                'status' => 'rolled_over',
                'rolled_over_booking_id' => $booking->id,
                'notes' => ($rollover->notes ? $rollover->notes."\n" : '').'Auto-rollover retried successfully by admin.',
            ]);

            // Notify User
            try {
                if ($booking->user) {
                    $booking->user->notify(new BookingRescheduledNotification(
                        $booking,
                        $oldStart,
                        $oldEnd,
                        'Admin retried and found a slot after initial rollover conflict.',
                        $block
                    ));
                }
            } catch (\Exception $e) {
                Log::error('Failed to notify user for retried rollover: '.$e->getMessage());
            }

            return [
                'success' => true,
                'message' => 'Rollover retried and slot found successfully.',
                'rollover' => $rollover->fresh(['rolledOverBooking']),
            ];
        }

        return [
            'success' => false,
            'message' => 'Still no available slot found for this booking.',
        ];
    }
}
