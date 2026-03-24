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
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreignId('qr_code_media_id')->nullable()->after('qr_code_path')
                ->constrained('media')->nullOnDelete();
        });

        Schema::table('event_orders', function (Blueprint $table) {
            $table->string('qr_code_path')->nullable()->after('qr_code_token');
            $table->foreignId('qr_code_media_id')->nullable()->after('qr_code_path')
                ->constrained('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_code_media_id');
        });

        Schema::table('event_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_code_media_id');
            $table->dropColumn('qr_code_path');
        });
    }
};
