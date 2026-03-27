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
            $table->foreignId('ticket_id')->nullable()->after('event_id')->constrained()->onDelete('set null');
        });

        // Data migration: Initialize ticket availability if it's null
        \DB::table('tickets')->whereNull('available')->update([
            'available' => \DB::raw('quantity')
        ]);
        
        // Also ensure all tickets have available count initialized if they were somehow missed
        \DB::table('tickets')->update(['available' => \DB::raw('CASE WHEN available IS NULL THEN quantity ELSE available END')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_orders', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropColumn('ticket_id');
        });
    }
};
