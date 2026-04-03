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
        Schema::table('slot_holds', function (Blueprint $table) {
            $table->decimal('total_price', 12, 2)->default(0)->after('series_id');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot_holds', function (Blueprint $table) {
            $table->dropColumn(['total_price', 'paid_amount']);
        });
    }
};
