<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship to the refundable (EventOrder, Donation, etc.)
            $table->string('refundable_type');
            $table->unsignedBigInteger('refundable_id');
            
            // The payment being refunded
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            
            // Admin/staff who processed the refund
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Amounts
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('KES');
            $table->decimal('original_amount', 10, 2)->comment('Original payment amount');
            
            // Status tracking
            $table->string('status', 30)->default('pending')
                ->comment('pending, approved, processing, completed, failed, rejected');
            
            // Reason and notes
            $table->string('reason', 100)->comment('cancellation, duplicate, customer_request, fraud, other');
            $table->text('customer_notes')->nullable()->comment('Customer-provided reason');
            $table->text('admin_notes')->nullable()->comment('Internal notes');
            
            // Gateway details
            $table->string('gateway')->nullable()->comment('pesapal, mpesa, manual, etc.');
            $table->string('gateway_refund_id')->nullable();
            $table->json('gateway_response')->nullable();
            
            // Timing
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['refundable_type', 'refundable_id']);
            $table->index('status');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
