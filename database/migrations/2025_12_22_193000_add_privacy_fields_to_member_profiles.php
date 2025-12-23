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
        Schema::table('member_profiles', function (Blueprint $table) {
            // Public profile settings
            $table->boolean('public_profile_enabled')->default(true);
            $table->text('public_bio')->nullable();
            
            // Privacy toggles - what to show on public profile
            $table->boolean('show_email')->default(false);
            $table->boolean('show_location')->default(true);
            $table->boolean('show_join_date')->default(true);
            $table->boolean('show_post_count')->default(true);
            $table->boolean('show_interests')->default(true);
            $table->boolean('show_occupation')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'public_profile_enabled',
                'public_bio',
                'show_email',
                'show_location',
                'show_join_date',
                'show_post_count',
                'show_interests',
                'show_occupation',
            ]);
        });
    }
};
