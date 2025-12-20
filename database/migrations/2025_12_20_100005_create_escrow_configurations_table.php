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
        Schema::create('escrow_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable(); // null for all
            $table->string('organizer_trust_level')->nullable(); // null for all
            $table->decimal('min_ticket_price', 12, 2)->default(0);
            $table->decimal('max_ticket_price', 12, 2)->nullable();
            $table->integer('hold_days_after_event')->default(3);
            $table->decimal('release_percentage_immediate', 5, 2)->default(0); // e.g. 50.00
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_configurations');
    }
};
