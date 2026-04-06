<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Sync data: Ensure capacity holds the intended concurrent user limit
        // We update capacity to match slots_per_hour where slots_per_hour was explicitly set
        DB::table('lab_spaces')
            ->whereNotNull('slots_per_hour')
            ->where('slots_per_hour', '>', 0)
            ->update(['capacity' => DB::raw('slots_per_hour')]);

        // 2. Drop the redundant column
        Schema::table('lab_spaces', function (Blueprint $table) {
            $table->dropColumn('slots_per_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_spaces', function (Blueprint $table) {
            $table->integer('slots_per_hour')->default(1)->after('capacity');
        });

        // Sync back (optional, but good for reversibility)
        DB::table('lab_spaces')->update(['slots_per_hour' => DB::raw('capacity')]);
    }
};
