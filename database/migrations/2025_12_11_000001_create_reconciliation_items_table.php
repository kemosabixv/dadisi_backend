<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconciliation_run_id');
            $table->string('transaction_id', 100)->nullable(); // transaction reference
            $table->string('reference', 100)->nullable(); // merchant reference
            $table->enum('source', ['app', 'gateway'])->default('app'); // which system it came from
            $table->dateTime('transaction_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('payer_name', 100)->nullable();
            $table->string('payer_phone', 20)->nullable();
            $table->string('payer_email', 100)->nullable();
            $table->string('county', 50)->nullable();
            $table->enum('app_status', ['pending', 'completed', 'failed', 'refunded'])->nullable();
            $table->enum('gateway_status', ['pending', 'completed', 'failed'])->nullable();
            $table->enum('reconciliation_status', ['matched', 'unmatched_app', 'unmatched_gateway', 'amount_mismatch', 'duplicate'])->default('unmatched_app');
            $table->string('match_reference', 100)->nullable(); // reference to matched item in other source
            $table->decimal('discrepancy_amount', 15, 2)->nullable(); // difference if amount mismatch
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // store extra fields, payment method, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('reconciliation_run_id')
                ->references('id')
                ->on('reconciliation_runs')
                ->onDelete('cascade');

            $table->index('reconciliation_run_id');
            $table->index('reconciliation_status');
            $table->index('transaction_id');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
    }
};
