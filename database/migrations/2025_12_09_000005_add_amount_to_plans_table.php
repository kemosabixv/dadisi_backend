<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = config('laravel-subscriptions.tables.plans', 'plans');
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'amount')) {
                    $table->decimal('amount', 12, 2)->default(0)->after('sort_order');
                }
            });
        }
    }

    public function down(): void
    {
        $tableName = config('laravel-subscriptions.tables.plans', 'plans');
        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'amount')) {
                    $table->dropColumn('amount');
                }
            });
        }
    }
};
