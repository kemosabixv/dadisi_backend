<?php

namespace App\Services\Reconciliation;

use App\Models\ReconciliationRun;
use App\Models\ReconciliationItem;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class ReconciliationService
{
    // Default matching tolerances
    protected float $amountPercentageTolerance = 0.01; // 1% tolerance
    protected float $amountAbsoluteTolerance = 0.0; // No absolute tolerance by default
    protected int $dateTolerance = 3; // 3 days tolerance
    protected int $fuzzyMatchThreshold = 80; // 80% similarity for fuzzy matching

    /**
     * Set amount percentage tolerance (as decimal, e.g., 0.01 for 1%).
     */
    public function setAmountPercentageTolerance(float $percentage): self
    {
        $this->amountPercentageTolerance = $percentage;
        return $this;
    }

    /**
     * Set absolute amount tolerance in currency units.
     */
    public function setAmountAbsoluteTolerance(float $amount): self
    {
        $this->amountAbsoluteTolerance = $amount;
        return $this;
    }

    /**
     * Set date tolerance in days.
     */
    public function setDateTolerance(int $days): self
    {
        $this->dateTolerance = $days;
        return $this;
    }

    /**
     * Set fuzzy match threshold (0-100, percentage similarity).
     */
    public function setFuzzyMatchThreshold(int $threshold): self
    {
        $this->fuzzyMatchThreshold = max(0, min(100, $threshold));
        return $this;
    }

    /**
     * Minimal, clean reconciliation runner used by tests.
     */
    public function runFromData(array $appTransactions, array $gatewayTransactions, array $options = []): ReconciliationRun
    {
        // Apply options to tolerances if provided
        if (isset($options['amount_percentage_tolerance'])) {
            $this->setAmountPercentageTolerance($options['amount_percentage_tolerance']);
        }
        if (isset($options['amount_absolute_tolerance'])) {
            $this->setAmountAbsoluteTolerance($options['amount_absolute_tolerance']);
        }
        if (isset($options['date_tolerance'])) {
            $this->setDateTolerance($options['date_tolerance']);
        }
        if (isset($options['fuzzy_match_threshold'])) {
            $this->setFuzzyMatchThreshold($options['fuzzy_match_threshold']);
        }

        $run = ReconciliationRun::create([
            'run_id' => (string) Str::uuid(),
            'started_at' => now(),
            'status' => 'running',
        ]);

        $gatewayByTxn = [];
        $gatewayByRef = [];
        $matchedGatewayIds = [];

        foreach ($gatewayTransactions as $idx => $g) {
            if (!empty($g['transaction_id'])) {
                $gatewayByTxn[$g['transaction_id']] = $idx;
            }
            if (!empty($g['reference'])) {
                $gatewayByRef[$g['reference']][] = $idx;
            }
        }

        foreach ($appTransactions as $a) {
            $isMatched = false;
            $matchedGatewayIdx = null;

            // Step 1: Try exact transaction_id match (highest priority)
            if (!empty($a['transaction_id']) && isset($gatewayByTxn[$a['transaction_id']])) {
                $matchedGatewayIdx = $gatewayByTxn[$a['transaction_id']];
                $g = $gatewayTransactions[$matchedGatewayIdx];
                if ($this->amountsMatch($a['amount'] ?? 0, $g['amount'] ?? 0)) {
                    $this->createMatchedItems($run, $a, $g);
                    $matchedGatewayIds[$matchedGatewayIdx] = true;
                    $isMatched = true;
                }
            }

            // Step 2: Try exact reference match with amount tolerance
            if (!$isMatched && !empty($a['reference']) && isset($gatewayByRef[$a['reference']])) {
                foreach ($gatewayByRef[$a['reference']] as $idx) {
                    if (!isset($matchedGatewayIds[$idx])) {
                        $g = $gatewayTransactions[$idx];
                        if ($this->amountsMatch($a['amount'] ?? 0, $g['amount'] ?? 0)) {
                            $this->createMatchedItems($run, $a, $g);
                            $matchedGatewayIds[$idx] = true;
                            $isMatched = true;
                            break;
                        }
                    }
                }
            }

            // Step 3: Try fuzzy reference match with amount and date tolerances
            if (!$isMatched && !empty($a['reference'])) {
                foreach ($gatewayTransactions as $idx => $g) {
                    if (!isset($matchedGatewayIds[$idx]) && !empty($g['reference'])) {
                        $similarity = $this->fuzzyMatch($a['reference'], $g['reference']);
                        if ($similarity >= $this->fuzzyMatchThreshold &&
                            $this->amountsMatch($a['amount'] ?? 0, $g['amount'] ?? 0) &&
                            $this->datesMatch($a['date'] ?? null, $g['date'] ?? null)) {
                            $this->createMatchedItems($run, $a, $g);
                            $matchedGatewayIds[$idx] = true;
                            $isMatched = true;
                            break;
                        }
                    }
                }
            }

            // Step 4: If no exact match found, try amount + date match only
            // Only do this if neither app nor gateway have reference (no identifiers to match on)
            if (!$isMatched && empty($a['reference'])) {
                foreach ($gatewayTransactions as $idx => $g) {
                    if (!isset($matchedGatewayIds[$idx]) && empty($g['reference'])) {
                        if ($this->amountsMatch($a['amount'] ?? 0, $g['amount'] ?? 0) &&
                            $this->datesMatch($a['date'] ?? null, $g['date'] ?? null)) {
                            $this->createMatchedItems($run, $a, $g);
                            $matchedGatewayIds[$idx] = true;
                            $isMatched = true;
                            break;
                        }
                    }
                }
            }

            if (!$isMatched) {
                $this->createItem($run, array_merge($a, ['source' => 'app', 'reconciliation_status' => 'unmatched_app']));
            }
        }

        // Add remaining unmatched gateway transactions
        foreach ($gatewayTransactions as $idx => $g) {
            if (!isset($matchedGatewayIds[$idx])) {
                $this->createItem($run, array_merge($g, ['source' => 'gateway', 'reconciliation_status' => 'unmatched_gateway']));
            }
        }


        // compute totals
        $totals = ReconciliationItem::where('reconciliation_run_id', $run->id)
            ->selectRaw("SUM(CASE WHEN reconciliation_status='matched' THEN 1 ELSE 0 END) as matched_items, SUM(CASE WHEN reconciliation_status='unmatched_app' THEN 1 ELSE 0 END) as total_unmatched_app, SUM(CASE WHEN reconciliation_status='unmatched_gateway' THEN 1 ELSE 0 END) as total_unmatched_gateway, SUM(CASE WHEN reconciliation_status='amount_mismatch' THEN 1 ELSE 0 END) as total_amount_mismatch, SUM(amount) as total_app_amount")
            ->first();

        $matchedItemCount = (int) ($totals->matched_items ?? 0);
        $matchedPairs = (int) floor($matchedItemCount / 2);

        $run->update([
            'total_matched' => $matchedPairs,
            'total_unmatched_app' => $totals->total_unmatched_app ?? 0,
            'total_unmatched_gateway' => $totals->total_unmatched_gateway ?? 0,
            'total_amount_mismatch' => $totals->total_amount_mismatch ?? 0,
            'total_app_amount' => $totals->total_app_amount ?? 0,
            'status' => 'success',
            'completed_at' => now(),
        ]);

        return $run->fresh();
    }

    /**
     * Check if two amounts match within configured tolerances.
     */
    protected function amountsMatch(float $amount1, float $amount2): bool
    {
        $diff = abs($amount1 - $amount2);

        // Check absolute tolerance
        if ($diff <= $this->amountAbsoluteTolerance) {
            return true;
        }

        // Check percentage tolerance (based on first amount)
        if ($amount1 > 0) {
            $percentageDiff = ($diff / abs($amount1)) * 100;
            if ($percentageDiff <= ($this->amountPercentageTolerance * 100)) {
                return true;
            }
        } elseif ($amount2 > 0) {
            // Fallback to second amount if first is zero
            $percentageDiff = ($diff / abs($amount2)) * 100;
            if ($percentageDiff <= ($this->amountPercentageTolerance * 100)) {
                return true;
            }
        } else {
            // Both are zero
            return true;
        }

        return false;
    }

    /**
     * Check if two dates match within configured tolerance (days).
     */
    protected function datesMatch(?string $date1, ?string $date2): bool
    {
        if (empty($date1) || empty($date2)) {
            return true; // No date constraint if either is missing
        }

        try {
            $d1 = Carbon::parse($date1);
            $d2 = Carbon::parse($date2);
            $daysDiff = abs($d1->diffInDays($d2));
            return $daysDiff <= $this->dateTolerance;
        } catch (\Throwable $e) {
            return true; // Treat parsing errors as match
        }
    }

    /**
     * Fuzzy match two strings using Levenshtein similarity.
     * Returns a percentage (0-100) indicating how similar the strings are.
     */
    protected function fuzzyMatch(string $str1, string $str2): int
    {
        // Normalize strings
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        // Exact match
        if ($str1 === $str2) {
            return 100;
        }

        // Use PHP's built-in levenshtein function
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 100;
        }

        $levenshtein = levenshtein($str1, $str2);
        $similarity = (int) (((float)($maxLen - $levenshtein) / $maxLen) * 100);

        return max(0, min(100, $similarity));
    }

    public function run(array $options = []): ReconciliationRun
    {
        $appTransactions = $options['app_transactions'] ?? [];
        $gatewayTransactions = $options['gateway_transactions'] ?? [];
        return $this->runFromData($appTransactions, $gatewayTransactions, $options);
    }

    protected function createMatchedItems(ReconciliationRun $run, array $a, array $g): void
    {
        $this->createItem($run, array_merge($a, [
            'source' => 'app',
            'reconciliation_status' => 'matched',
            'linked_transaction_id' => $g['transaction_id'] ?? null,
        ]));

        $this->createItem($run, array_merge($g, [
            'source' => 'gateway',
            'reconciliation_status' => 'matched',
            'linked_transaction_id' => $a['transaction_id'] ?? null,
        ]));
    }

    protected function createItem(ReconciliationRun $run, array $payload): ReconciliationItem
    {
        return $run->items()->create([
            'transaction_id' => $payload['transaction_id'] ?? null,
            'reference' => $payload['reference'] ?? null,
            'amount' => $payload['amount'] ?? 0,
            'source' => $payload['source'] ?? null,
            'reconciliation_status' => $payload['reconciliation_status'] ?? 'unmatched',
            'metadata' => $payload['metadata'] ?? null,
            'linked_transaction_id' => $payload['linked_transaction_id'] ?? null,
        ]);
    }

    protected function existsInRun(ReconciliationRun $run, ?string $transactionId): bool
    {
        if (empty($transactionId)) return false;
        return $run->items()->where('transaction_id', $transactionId)->exists();
    }
}
