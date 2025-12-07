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
        Schema::create('renewal_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->enum('renewal_type', ['automatic', 'manual'])->default('automatic');
            $table->boolean('send_renewal_reminders')->default(true);
            $table->integer('reminder_days_before')->default(7);
            $table->string('preferred_payment_method')->nullable();
            $table->boolean('auto_switch_to_free_on_expiry')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('renewal_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_preferences');
    }
};
