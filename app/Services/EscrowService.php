<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EscrowConfiguration;
use App\Models\SystemSetting;
use Carbon\Carbon;

class EscrowService
{
    /**
     * Get the default hold days from system settings.
     */
    public function getDefaultHoldDays(): int
    {
        $setting = SystemSetting::where('key', 'escrow_default_hold_days')->first();
        
        return $setting ? (int) $setting->value : 3;
    }

    /**
     * Calculate the date until which the payout should be held.
     */
    public function calculateHoldUntil(Event $event): Carbon
    {
        $holdDays = $this->determineHoldDays($event);
        
        // Base hold calculation from event end date
        $endDate = $event->ends_at ?? $event->starts_at ?? now();
        
        return Carbon::parse($endDate)->addDays($holdDays);
    }

    /**
     * Determine how many days to hold funds after the event ends.
     * Logic: Most restrictive rule applies.
     */
    public function determineHoldDays(Event $event): int
    {
        $organizer = $event->organizer;
        
        // Default hold days from system settings
        $maxHoldDays = $this->getDefaultHoldDays();

        // Check for event-specific escrow configurations
        $configs = EscrowConfiguration::where('is_active', true)->get();
        
        foreach ($configs as $config) {
            $applies = false;
            
            // Event type matching
            if ($config->event_type && $event->event_type === $config->event_type) {
                $applies = true;
            }
            
            // Organizer trust level (if implemented on User model)
            if ($config->organizer_trust_level && $organizer) {
                $trustLevel = $organizer->trust_level ?? 'standard';
                if ($trustLevel === $config->organizer_trust_level) {
                    $applies = true;
                }
            }
            
            // Ticket price range
            if ($config->min_ticket_price || $config->max_ticket_price) {
                $avgPrice = $event->tickets()->avg('price') ?: ($event->price ?? 0);
                
                $minPrice = $config->min_ticket_price ?? 0;
                $maxPrice = $config->max_ticket_price ?? PHP_FLOAT_MAX;
                
                if ($avgPrice >= $minPrice && $avgPrice <= $maxPrice) {
                    $applies = true;
                }
            }
            
            // Apply more restrictive (longer) hold period
            if ($applies && $config->hold_days_after_event > $maxHoldDays) {
                $maxHoldDays = $config->hold_days_after_event;
            }
        }

        return $maxHoldDays;
    }

    /**
     * Check if funds are ready for release.
     */
    public function isReadyForRelease(Event $event): bool
    {
        $holdUntil = $this->calculateHoldUntil($event);
        
        return now()->gte($holdUntil);
    }

    /**
     * Calculate the release percentage based on event configuration.
     * Returns what percentage should be released immediately vs held.
     */
    public function getReleasePercentage(Event $event): array
    {
        // Find matching configuration
        $config = EscrowConfiguration::where('is_active', true)
            ->where(function ($query) use ($event) {
                $query->where('event_type', $event->event_type)
                    ->orWhereNull('event_type');
            })
            ->orderBy('release_percentage_immediate', 'asc')
            ->first();

        $immediatePercent = $config?->release_percentage_immediate ?? 100;

        return [
            'immediate' => (float) $immediatePercent,
            'held' => 100 - (float) $immediatePercent,
        ];
    }
}
