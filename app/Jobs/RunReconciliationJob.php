<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Reconciliation\ReconciliationService;

class RunReconciliationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $appTransactions;
    public array $gatewayTransactions;
    public array $options;
    public ?int $createdBy;

    /**
     * Create a new job instance.
     */
    public function __construct(array $appTransactions = [], array $gatewayTransactions = [], array $options = [], ?int $createdBy = null)
    {
        $this->appTransactions = $appTransactions;
        $this->gatewayTransactions = $gatewayTransactions;
        $this->options = $options;
        $this->createdBy = $createdBy;
    }

    /**
     * Execute the job.
     */
    public function handle(ReconciliationService $service): void
    {
        $run = $service->runFromData($this->appTransactions, $this->gatewayTransactions, $this->options);

        // Record who triggered this run
        if ($this->createdBy) {
            $run->update(['created_by' => $this->createdBy]);
        }
    }
}
