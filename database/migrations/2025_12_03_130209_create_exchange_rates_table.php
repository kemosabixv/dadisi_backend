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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3); // USD
            $table->string('to_currency', 3);   // KES
            $table->decimal('rate', 10, 6);     // USD to KES rate
            $table->decimal('inverse_rate', 10, 6); // KES to USD rate
            $table->integer('cache_minutes')->default(10080); // 7 days
            $table->timestamp('last_updated');
            $table->timestamps();

            $table->index(['from_currency', 'to_currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
