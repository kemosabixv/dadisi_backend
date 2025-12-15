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
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 36)->unique(); // UUID-like run identifier
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['pending', 'running', 'success', 'partial', 'failed'])->default('pending');
            $table->date('period_start')->nullable(); // reconciliation period start
            $table->date('period_end')->nullable(); // reconciliation period end
            $table->string('county')->nullable(); // optional county filter
            $table->integer('total_matched')->default(0);
            $table->integer('total_unmatched_app')->default(0);
            $table->integer('total_unmatched_gateway')->default(0);
            $table->integer('total_amount_mismatch')->default(0);
            $table->decimal('total_app_amount', 15, 2)->default(0);
            $table->decimal('total_gateway_amount', 15, 2)->default(0);
            $table->decimal('total_discrepancy', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // store run config, source type, etc.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('period_start');
            $table->index('county');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
