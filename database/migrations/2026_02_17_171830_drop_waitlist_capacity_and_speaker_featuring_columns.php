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
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('waitlist_capacity');
        });

        Schema::table('speakers', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->integer('waitlist_capacity')->nullable()->after('waitlist_enabled');
        });

        Schema::table('speakers', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('linkedin_url');
        });
    }
};
