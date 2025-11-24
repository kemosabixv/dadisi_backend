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
        // Drop existing table if it exists with different structure
        Schema::dropIfExists('audit_logs');

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action'); // create, update, delete, restore, login, etc.
            $table->string('model_type'); // User, MemberProfile, etc.
            $table->unsignedBigInteger('model_id'); // ID of the affected record
            $table->unsignedBigInteger('user_id')->nullable(); // Who performed the action
            $table->json('old_values')->nullable(); // Previous values
            $table->json('new_values')->nullable(); // New values
            $table->string('ip_address')->nullable(); // IP address
            $table->text('user_agent')->nullable(); // Browser/client info
            $table->text('notes')->nullable(); // Additional context
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index(['user_id']);
            $table->index(['action']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
