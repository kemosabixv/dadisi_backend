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
        Schema::create('lab_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_space_id')->constrained('lab_spaces')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Booking Details
            $table->string('title')->nullable();
            $table->text('purpose');

            // Time Slot
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('slot_type', ['hourly', 'half_day', 'full_day'])->default('hourly');

            // Recurrence (optional)
            $table->string('recurrence_rule')->nullable(); // iCal RRULE format
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('lab_bookings')->onDelete('set null');

            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed', 'no_show'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // Attendance
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->decimal('actual_duration_hours', 5, 2)->nullable();

            // Quota Tracking
            $table->boolean('quota_consumed')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index('status');
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index(['user_id', 'status']);
            $table->index(['lab_space_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_bookings');
    }
};
