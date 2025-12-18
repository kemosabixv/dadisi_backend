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
        $table = config('laravel-subscriptions.tables.plans', 'plans');

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
            if (!Schema::hasColumn($table, 'monthly_promotion_discount_percent')) {
                $tableBlueprint->decimal('monthly_promotion_discount_percent', 5, 2)->default(0)->after('sort_order');
            }

            if (!Schema::hasColumn($table, 'monthly_promotion_expires_at')) {
                $tableBlueprint->timestamp('monthly_promotion_expires_at')->nullable()->after('monthly_promotion_discount_percent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = config('laravel-subscriptions.tables.plans', 'plans');

        Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
            if (Schema::hasColumn($table, 'monthly_promotion_discount_percent')) {
                $tableBlueprint->dropColumn('monthly_promotion_discount_percent');
            }

            if (Schema::hasColumn($table, 'monthly_promotion_expires_at')) {
                $tableBlueprint->dropColumn('monthly_promotion_expires_at');
            }
        });
    }
};
