<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'start_at')) {
                    $table->timestamp('start_at')->nullable()->after('description');
                }
                if (!Schema::hasColumn('events', 'end_at')) {
                    $table->timestamp('end_at')->nullable()->after('start_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (Schema::hasColumn('events', 'start_at')) {
                    $table->dropColumn('start_at');
                }
                if (Schema::hasColumn('events', 'end_at')) {
                    $table->dropColumn('end_at');
                }
            });
        }
    }
};
