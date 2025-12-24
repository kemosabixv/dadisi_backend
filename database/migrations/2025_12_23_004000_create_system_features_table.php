<?php

declare(strict_types=1);

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
        // System features table - built-in features that cannot be deleted
        Schema::create('system_features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('value_type')->default('number'); // number, boolean
            $table->string('default_value')->default('0');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Pivot table linking system features to plans with custom values
        Schema::create('plan_system_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_feature_id')->constrained()->cascadeOnDelete();
            $table->string('value'); // The quota/setting value for this plan
            $table->string('display_name')->nullable(); // Custom display name for this plan
            $table->text('display_description')->nullable(); // Custom description for pricing page
            $table->timestamps();

            $table->unique(['plan_id', 'system_feature_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_system_feature');
        Schema::dropIfExists('system_features');
    }
};
