<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scheduler_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('command_name')->unique(); // e.g., 'media:cleanup'
            $table->string('run_time')->default('03:00'); // Time in HH:MM format
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'hourly'])->default('daily');
            $table->boolean('enabled')->default(true);
            $table->string('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_settings');
    }
};
