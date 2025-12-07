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
            // Only add columns if they don't exist
            if (!Schema::hasColumn('member_profiles', 'plan_type')) {
                $table->enum('plan_type', ['free', 'premium', 'student', 'corporate'])->default('free');
            }
            if (!Schema::hasColumn('member_profiles', 'plan_expires_at')) {
                $table->timestamp('plan_expires_at')->nullable();
            }
            // plan_id already exists from earlier migrations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('plan_type');
            $table->dropColumn('plan_expires_at');
        });
    }
};
