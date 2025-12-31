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
        Schema::create('lab_spaces', function (Blueprint $table) {
            $table->id();
            
            // Core Fields
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('capacity')->default(1);
            $table->string('county')->nullable();
            
            // Lab Type and Display
            $table->enum('type', ['dry_lab', 'wet_lab', 'greenhouse', 'mobile_lab', 'makerspace', 'workshop', 'studio', 'other'])->default('dry_lab');
            $table->string('image_path')->nullable();
            $table->json('safety_requirements')->nullable();
            
            // Availability
            $table->boolean('is_available')->default(true);
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            
            // Metadata
            $table->json('equipment_list')->nullable();
            $table->text('rules')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('is_available');
            $table->index('county');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_spaces');
    }
};
