<?php

namespace Tests\Unit;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Reconciliation\ReconciliationService;
use App\Models\ReconciliationRun;
use Illuminate\Support\Str;

class ReconciliationServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    #[Test]
    public function test_run_from_data_matches_exact_reference()
    {
        $service = new ReconciliationService();

        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF1', 'amount' => 100.00, 'date' => now()->toIso8601String()],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF1', 'amount' => 100.00, 'date' => now()->toIso8601String()],
        ];

        $run = $service->runFromData($app, $gateway);

        $this->assertInstanceOf(ReconciliationRun::class, $run);
        $this->assertEquals(2, $run->items()->count()); // both app and gateway item created
        $this->assertEquals(1, $run->total_matched);
    }

    #[Test]
    public function test_run_from_data_flags_unmatched_gateway()
    {
        $service = new ReconciliationService();

        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF1', 'amount' => 100.00, 'date' => now()->toIso8601String()],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF_OTHER', 'amount' => 50.00, 'date' => now()->toIso8601String()],
        ];

        $run = $service->runFromData($app, $gateway);

        // Both app and gateway are recorded as unmatched when they don't match,
        // so the total of matched + unmatched_app + unmatched_gateway should be 2.
        $this->assertEquals(2, $run->total_unmatched_gateway + $run->total_unmatched_app + $run->total_matched);
    }
}
