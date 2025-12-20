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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('organizer_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('payout_amount', 12, 2);
            $table->char('currency', 3)->default('KES');
            $table->enum('status', ['pending', 'on_hold', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->timestamp('hold_until')->nullable();
            $table->string('reference')->unique()->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
