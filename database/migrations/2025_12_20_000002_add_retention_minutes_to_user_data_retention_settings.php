<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_data_retention_settings', function (Blueprint $table) {
            // Add retention_minutes for finer control (e.g., temporary media at 30 minutes)
            $table->integer('retention_minutes')->nullable()->after('retention_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_data_retention_settings', function (Blueprint $table) {
            $table->dropColumn('retention_minutes');
        });
    }
};
