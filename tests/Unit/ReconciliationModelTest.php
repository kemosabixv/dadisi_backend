<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\ReconciliationRun;
use App\Models\ReconciliationItem;

class ReconciliationModelTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_reconciliation_run_factory_creates_model()
    {
        $run = ReconciliationRun::factory()->create();

        $this->assertNotNull($run->id);
        $this->assertNotNull($run->run_id);
        $this->assertEquals('success', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);
    }

    public function test_reconciliation_run_has_items()
    {
        $run = ReconciliationRun::factory()->create();
        $items = ReconciliationItem::factory(3)->create([
            'reconciliation_run_id' => $run->id,
        ]);

        $this->assertCount(3, $run->items);
        $this->assertEquals($items[0]->id, $run->items[0]->id);
    }

    public function test_reconciliation_item_factory_creates_model()
    {
        $item = ReconciliationItem::factory()->create();

        $this->assertNotNull($item->id);
        $this->assertNotNull($item->reconciliation_run_id);
        $this->assertContains($item->source, ['app', 'gateway']);
        $this->assertContains($item->reconciliation_status, ['matched', 'unmatched_app', 'unmatched_gateway']);
    }

    public function test_reconciliation_item_mark_matched()
    {
        $item = ReconciliationItem::factory()->unmatchedApp()->create();
        $this->assertEquals('unmatched_app', $item->reconciliation_status);

        $item->markMatched('REF_12345');
        $this->assertEquals('matched', $item->reconciliation_status);
        $this->assertEquals('REF_12345', $item->match_reference);
    }

    public function test_reconciliation_run_add_item()
    {
        $run = ReconciliationRun::factory()->create();
        $item = $run->addItem([
            'transaction_id' => 'TXN_123',
            'reference' => 'REF_456',
            'source' => 'app',
            'amount' => 100.00,
            'reconciliation_status' => 'matched',
        ]);

        $this->assertEquals($run->id, $item->reconciliation_run_id);
        $this->assertEquals('TXN_123', $item->transaction_id);
    }

    public function test_reconciliation_run_mark_completed()
    {
        $run = ReconciliationRun::factory()->running()->create();
        $this->assertNull($run->completed_at);

        $run->markCompleted('success');
        $this->assertEquals('success', $run->status);
        $this->assertNotNull($run->completed_at);
    }
}
