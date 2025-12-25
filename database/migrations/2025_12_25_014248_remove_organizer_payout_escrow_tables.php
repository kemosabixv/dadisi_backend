<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration removes:
     * - organizer_id column from events table (all events are now staff-created)
     * - payouts table (no more external organizer payouts)
     * - escrow_configurations table (no more escrow system)
     */
    public function up(): void
    {
        // Drop organizer_id foreign key and column from events
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['organizer_id']);
            $table->dropColumn('organizer_id');
        });

        // Drop payouts table
        Schema::dropIfExists('payouts');

        // Drop escrow_configurations table
        Schema::dropIfExists('escrow_configurations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore organizer_id column to events
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
        });

        // Restore payouts table
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('organizer_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Restore escrow_configurations table
        Schema::create('escrow_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable();
            $table->string('organizer_trust_level')->nullable();
            $table->decimal('max_ticket_price', 12, 2)->nullable();
            $table->integer('hold_days')->default(7);
            $table->decimal('automatic_release_percentage', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
};
