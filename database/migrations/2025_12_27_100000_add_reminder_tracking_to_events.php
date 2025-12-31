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

        Schema::table('event_orders', function (Blueprint $table) {
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamp('reminded_1h_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('event_orders', function (Blueprint $table) {
            $table->dropColumn(['reminded_24h_at', 'reminded_1h_at']);
        });
    }
};
