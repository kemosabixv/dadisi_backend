<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EscrowConfiguration;
use Carbon\Carbon;

class EscrowService
{
    /**
     * Calculate the date until which the payout should be held.
     */
    public function calculateHoldUntil(Event $event): Carbon
    {
        $holdDays = $this->determineHoldDays($event);
        
        // Base hold calculation from event end date
        $endDate = $event->ends_at ?? now();
        
        return Carbon::parse($endDate)->addDays($holdDays);
    }

    /**
     * Determine how many days to hold funds after the event ends.
     * Logic: Most restrictive rule applies.
     */
    public function determineHoldDays(Event $event): int
    {
        $organizer = $event->organizer;
        
        // Default hold days
        $maxHoldDays = 3;

        // 1. Check event type rules (simulated by capacity/category for now as event_type is not a field yet)
        // In a real scenario, we'd have an event_type column.
        // For now let's use some heuristics or look for configurations.
        
        $configs = EscrowConfiguration::where('is_active', true)->get();
        
        foreach ($configs as $config) {
            $applies = false;
            
            // Organizer trust level (if implemented on User model)
            if ($config->organizer_trust_level && $organizer->trust_level === $config->organizer_trust_level) {
                $applies = true;
            }
            
            // Ticket price range
            if ($config->min_ticket_price || $config->max_ticket_price) {
                $avgPrice = $event->tickets()->avg('price') ?: 0;
                if ($avgPrice >= $config->min_ticket_price && ($config->max_ticket_price === null || $avgPrice <= $config->max_ticket_price)) {
                    $applies = true;
                }
            }
            
            if ($applies && $config->hold_days_after_event > $maxHoldDays) {
                $maxHoldDays = $config->hold_days_after_event;
            }
        }

        return $maxHoldDays;
    }
}
