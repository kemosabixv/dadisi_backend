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
        // 1. Update lab_maintenance_blocks
        Schema::table('lab_maintenance_blocks', function (Blueprint $table) {
            // Change recurrence_rule from string (iCal RRULE) to text/json for our structured seeds
            $table->text('recurrence_rule')->nullable()->change();
            
            // Add recurrence_parent_id to link instances to a master/series block
            $table->foreignId('recurrence_parent_id')
                ->nullable()
                ->after('recurrence_rule')
                ->constrained('lab_maintenance_blocks')
                ->onDelete('cascade');
        });

        // 2. Update maintenance_block_rollovers
        Schema::table('maintenance_block_rollovers', function (Blueprint $table) {
            // Add series_id to group rollovers by their maintenance series
            $table->unsignedBigInteger('series_id')->nullable()->after('maintenance_block_id');
            $table->index('series_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_block_rollovers', function (Blueprint $table) {
            $table->dropColumn('series_id');
        });

        Schema::table('lab_maintenance_blocks', function (Blueprint $table) {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropColumn('recurrence_parent_id');
            $table->string('recurrence_rule')->nullable()->change();
        });
    }
};
