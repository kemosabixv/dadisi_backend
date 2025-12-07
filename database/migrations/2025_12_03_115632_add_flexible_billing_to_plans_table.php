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
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('base_monthly_price', 10, 2)->nullable()->after('price');
            $table->decimal('yearly_discount_percent', 5, 2)->default(20.00)->after('base_monthly_price');
            $table->integer('default_billing_period')->default(1)->after('yearly_discount_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['base_monthly_price', 'yearly_discount_percent', 'default_billing_period']);
        });
    }
};
