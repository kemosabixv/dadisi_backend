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
        Schema::create('maintenance_block_rollovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_block_id')->constrained('lab_maintenance_blocks')->onDelete('cascade');
            $table->foreignId('original_booking_id')->constrained('lab_bookings')->onDelete('cascade');
            $table->foreignId('rolled_over_booking_id')->nullable()->constrained('lab_bookings')->onDelete('set null');
            $table->enum('status', ['pending', 'rolled_over', 'cancelled'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('maintenance_block_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_block_rollovers');
    }
};
