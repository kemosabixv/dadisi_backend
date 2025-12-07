<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Name components
            $table->string('first_name', 100);
            $table->string('last_name', 100);

            // Personal details
            $table->string('phone_number', 15)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->foreignId('county_id')->nullable()->constrained('counties')->cascadeOnDelete();
            $table->string('sub_county', 50)->nullable();
            $table->string('ward', 50)->nullable();

            // Profile content
            $table->json('interests')->nullable();
            $table->text('bio')->nullable();

            // Staff status
            $table->boolean('is_staff')->default(false);

            // Membership
            $table->unsignedBigInteger('plan_id')->nullable();

            // Additional details
            $table->string('occupation')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 15)->nullable();

            // Legal consents
            $table->boolean('terms_accepted')->default(false);
            $table->boolean('marketing_consent')->default(false);

            $table->timestamps();

            // Soft deletes for member profiles (used by seeders and model SoftDeletes)
            $table->softDeletes();

            // Indexes
            $table->index('county_id', 'idx_county_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};
