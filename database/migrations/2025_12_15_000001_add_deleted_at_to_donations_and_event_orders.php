<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('donations')) {
            Schema::table('donations', function (Blueprint $table) {
                if (!Schema::hasColumn('donations', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at');
                }
            });
        }

        if (Schema::hasTable('event_orders')) {
            Schema::table('event_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('event_orders', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasTable('donations')) {
            Schema::table('donations', function (Blueprint $table) {
                if (Schema::hasColumn('donations', 'deleted_at')) {
                    $table->dropColumn('deleted_at');
                }
            });
        }

        if (Schema::hasTable('event_orders')) {
            Schema::table('event_orders', function (Blueprint $table) {
                if (Schema::hasColumn('event_orders', 'deleted_at')) {
                    $table->dropColumn('deleted_at');
                }
            });
        }
    }
};
