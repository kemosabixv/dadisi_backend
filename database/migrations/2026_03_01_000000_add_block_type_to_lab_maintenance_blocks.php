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
        Schema::table('lab_maintenance_blocks', function (Blueprint $table) {
            $table->enum('block_type', ['maintenance', 'holiday', 'closure'])
                ->default('maintenance')
                ->after('reason')
                ->comment('Type of block: maintenance, holiday, or closure');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_maintenance_blocks', function (Blueprint $table) {
            $table->dropColumn('block_type');
        });
    }
};
