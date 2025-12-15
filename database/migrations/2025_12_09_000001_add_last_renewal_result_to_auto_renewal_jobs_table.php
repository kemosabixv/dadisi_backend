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
        if (Schema::hasTable('auto_renewal_jobs')) {
            Schema::table('auto_renewal_jobs', function (Blueprint $table) {
                $table->string('last_renewal_result')->nullable()->after('payment_gateway_response')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('auto_renewal_jobs')) {
            Schema::table('auto_renewal_jobs', function (Blueprint $table) {
                $table->dropIndex(['last_renewal_result']);
                $table->dropColumn('last_renewal_result');
            });
        }
    }
};
