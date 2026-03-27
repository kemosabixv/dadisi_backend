<?php

namespace App\Services\LabManagement;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SeriesExpansionService
 * 
 * Handles the expansion of "Multi-Seed" recurrence rules into concrete date/time windows.
 * Supports Weekly, Monthly, and Yearly frequencies with specific start/end times per seed.
 */
class SeriesExpansionService
{
    /**
     * Expand a recurrence rule into a collection of time windows.
     * 
     * @param array $rule The recurrence rule JSON
     * @param Carbon $dtStart The start date of the series
     * @return Collection Collection of ['starts_at' => Carbon, 'ends_at' => Carbon, 'full_day' => bool]
     */
    public function expandRule(array $rule, Carbon $dtStart): Collection
    {
        $freq = $rule['freq'] ?? 'weekly';
        $interval = $rule['interval'] ?? 1;
        $seeds = $rule['seeds'] ?? [];
        $until = isset($rule['until']) ? Carbon::parse($rule['until'])->endOfDay() : $dtStart->copy()->addYear()->endOfDay();
        
        // Safeguard: Max 5 years
        if ($until->diffInYears($dtStart) > 5) {
            $until = $dtStart->copy()->addYears(5)->endOfDay();
        }

        $instances = collect();
        $current = $dtStart->copy()->startOfDay();

        while ($current <= $until) {
            foreach ($seeds as $seed) {
                $instance = $this->resolveInstanceFromSeed($current, $freq, $seed);
                
                if ($instance && $instance['starts_at'] <= $until && $instance['starts_at'] >= $dtStart->startOfDay()) {
                    $instances->push($instance);
                }
            }

            // Advance based on frequency and interval
            match ($freq) {
                'weekly' => $current->addWeeks($interval),
                'monthly' => $current->addMonths($interval),
                'yearly' => $current->addYears($interval),
                default => $current->addDay(),
            };
        }

        // De-duplicate and sort
        return $instances->unique(fn($i) => $i['starts_at']->toDateTimeString())
            ->sortBy('starts_at')
            ->values();
    }

    /**
     * Resolve a specific instance from a seed and a current reference date.
     */
    private function resolveInstanceFromSeed(Carbon $current, string $freq, array $seed): ?array
    {
        $point = $seed['point'] ?? null;
        if (!$point) return null;

        $targetDate = $current->copy();

        try {
            switch ($freq) {
                case 'weekly':
                    // point is "MO", "TU", etc.
                    $dayMap = [
                        'SU' => Carbon::SUNDAY,
                        'MO' => Carbon::MONDAY,
                        'TU' => Carbon::TUESDAY,
                        'WE' => Carbon::WEDNESDAY,
                        'TH' => Carbon::THURSDAY,
                        'FR' => Carbon::FRIDAY,
                        'SA' => Carbon::SATURDAY,
                    ];
                    $targetDay = $dayMap[strtoupper($point)] ?? null;
                    if ($targetDay === null) return null;
                    $targetDate->startOfWeek()->addDays(($targetDay - Carbon::MONDAY + 7) % 7);
                    break;

                case 'monthly':
                    // point is day index 1-31
                    $targetDate->setDay((int)$point);
                    break;

                case 'yearly':
                    // point is "MM-DD"
                    $parts = explode('-', $point);
                    if (count($parts) !== 2) return null;
                    $targetDate->setMonth((int)$parts[0])->setDay((int)$parts[1]);
                    break;

                default:
                    return null;
            }
        } catch (\Exception $e) {
            // Handle invalid dates (e.g., Feb 30th) by skipping
            return null;
        }

        // Apply times
        return $this->applyTimingToDate($targetDate, $seed);
    }

    /**
     * Apply start/end times or full-day status to a resolved date.
     */
    private function applyTimingToDate(Carbon $date, array $seed): array
    {
        $fullDay = $seed['full_day'] ?? true;
        
        if ($fullDay) {
            return [
                'starts_at' => $date->copy()->startOfDay(),
                'ends_at' => $date->copy()->endOfDay(),
                'full_day' => true
            ];
        }

        $startStr = $seed['start'] ?? '00:00';
        $endStr = $seed['end'] ?? '23:59';

        $startsAt = $date->copy();
        $endsAt = $date->copy();

        try {
            $startParts = explode(':', $startStr);
            $endParts = explode(':', $endStr);

            $startsAt->setHour((int)($startParts[0] ?? 0))->setMinute((int)($startParts[1] ?? 0))->setSecond(0);
            $endsAt->setHour((int)($endParts[0] ?? 23))->setMinute((int)($endParts[1] ?? 59))->setSecond(59);

            // Handle overnight blocks if needed (though usually maintenance stays within a day or is multi-day)
            if ($endsAt < $startsAt) {
                $endsAt->addDay();
            }
        } catch (\Exception $e) {
            $startsAt->startOfDay();
            $endsAt->endOfDay();
        }

        return [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'full_day' => false
        ];
    }
}
