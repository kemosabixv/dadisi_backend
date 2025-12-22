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
        Schema::create('lab_maintenance_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_space_id')->constrained('lab_spaces')->onDelete('cascade');
            $table->string('title');
            $table->text('reason')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('recurring')->default(false);
            $table->string('recurrence_rule')->nullable(); // iCal RRULE format
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes for availability checks
            $table->index(['lab_space_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_maintenance_blocks');
    }
};
