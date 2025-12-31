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
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained('event_orders')->onDelete('set null');
            $table->string('confirmation_code')->unique();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'attended', 'waitlisted'])->default('pending');
            $table->timestamp('check_in_at')->nullable();
            $table->integer('waitlist_position')->nullable();
            $table->string('qr_code_token')->unique()->nullable();
            $table->string('qr_code_path')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_1h_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
