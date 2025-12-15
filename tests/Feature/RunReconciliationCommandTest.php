<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationItem;

class RunReconciliationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_with_file_inputs_rolls_back_database_changes()
    {
        // prepare temp files
        $tmpDir = base_path('tests/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF1', 'amount' => 100.00, 'date' => now()->toIso8601String()],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF1', 'amount' => 100.00, 'date' => now()->toIso8601String()],
        ];

        $appFile = $tmpDir . DIRECTORY_SEPARATOR . 'app_tx.json';
        $gatewayFile = $tmpDir . DIRECTORY_SEPARATOR . 'gateway_tx.json';

        file_put_contents($appFile, json_encode($app));
        file_put_contents($gatewayFile, json_encode($gateway));

        // ensure no runs exist
        $this->assertSame(0, ReconciliationRun::count());
        $this->assertSame(0, ReconciliationItem::count());

        // run dry-run command
        $this->artisan('reconciliation:run', [
            '--dry-run' => true,
            '--app-file' => $appFile,
            '--gateway-file' => $gatewayFile,
        ])->assertExitCode(0);

        // dry-run should have rolled back DB changes
        $this->assertSame(0, ReconciliationRun::count());
        $this->assertSame(0, ReconciliationItem::count());

        // cleanup
        @unlink($appFile);
        @unlink($gatewayFile);
    }

    public function test_sync_command_with_file_inputs_creates_database_records()
    {
        // prepare temp files
        $tmpDir = base_path('tests/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $app = [
            ['transaction_id' => 'A1', 'reference' => 'REF1', 'amount' => 100.00, 'date' => now()->toIso8601String()],
        ];
        $gateway = [
            ['transaction_id' => 'G1', 'reference' => 'REF1', 'amount' => 100.00, 'date' => now()->toIso8601String()],
        ];

        $appFile = $tmpDir . DIRECTORY_SEPARATOR . 'app_tx_sync.json';
        $gatewayFile = $tmpDir . DIRECTORY_SEPARATOR . 'gateway_tx_sync.json';

        file_put_contents($appFile, json_encode($app));
        file_put_contents($gatewayFile, json_encode($gateway));

        // ensure no runs exist
        $this->assertSame(0, ReconciliationRun::count());
        $this->assertSame(0, ReconciliationItem::count());

        // run command with --sync (synchronous, not queued)
        $this->artisan('reconciliation:run', [
            '--sync' => true,
            '--app-file' => $appFile,
            '--gateway-file' => $gatewayFile,
        ])->assertExitCode(0);

        // verify run and items were created
        $this->assertSame(1, ReconciliationRun::count());
        $run = ReconciliationRun::first();
        $this->assertNotNull($run);
        $this->assertSame(1, $run->total_matched);

        // verify items exist (matched pair: app + gateway = 2 items)
        $this->assertSame(2, ReconciliationItem::count());
        $items = ReconciliationItem::where('reconciliation_run_id', $run->id)->get();
        $this->assertSame(2, $items->count());

        // verify both items are marked as matched
        $matchedCount = $items->where('reconciliation_status', 'matched')->count();
        $this->assertSame(2, $matchedCount);

        // cleanup
        @unlink($appFile);
        @unlink($gatewayFile);
    }
}
