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
            $table->boolean('display_full_name')->default(false)->after('public_profile_enabled');
            $table->boolean('display_age')->default(false)->after('display_full_name');
            $table->string('prefix')->nullable()->after('display_age');
            $table->string('public_role')->nullable()->after('prefix');
            
            $table->json('experience')->nullable()->after('public_role');
            $table->boolean('experience_visible')->default(false)->after('experience');
            
            $table->json('education')->nullable()->after('experience_visible');
            $table->boolean('education_visible')->default(false)->after('education');
            
            $table->json('skills')->nullable()->after('education_visible');
            $table->boolean('skills_visible')->default(false)->after('skills');
            
            $table->json('achievements')->nullable()->after('skills_visible');
            $table->boolean('achievements_visible')->default(false)->after('achievements');
            
            $table->json('certifications')->nullable()->after('achievements_visible');
            $table->boolean('certifications_visible')->default(false)->after('certifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'display_full_name',
                'display_age',
                'prefix',
                'public_role',
                'experience',
                'experience_visible',
                'education',
                'education_visible',
                'skills',
                'skills_visible',
                'achievements',
                'achievements_visible',
                'certifications',
                'certifications_visible',
            ]);
        });
    }
};
