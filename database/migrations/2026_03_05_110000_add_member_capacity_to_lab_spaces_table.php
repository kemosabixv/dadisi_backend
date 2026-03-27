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
        Schema::table('lab_spaces', function (Blueprint $table) {
            $table->integer('member_capacity')->default(0)->after('capacity')->comment('Maximum members allowed for group bookings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_spaces', function (Blueprint $table) {
            $table->dropColumn('member_capacity');
        });
    }
};
