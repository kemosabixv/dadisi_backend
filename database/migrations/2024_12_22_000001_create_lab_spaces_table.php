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
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['wet_lab', 'dry_lab', 'greenhouse', 'mobile_lab']);
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity')->default(4);
            $table->string('image_path')->nullable();
            $table->json('amenities')->nullable();
            $table->json('safety_requirements')->nullable();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
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
