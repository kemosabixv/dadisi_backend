<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_bookings', function (Blueprint $table) {
            // Make user_id nullable for guest bookings
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Guest fields
            $table->string('guest_name', 255)->nullable()->after('user_id');
            $table->string('guest_email', 255)->nullable()->after('guest_name');

            // Payment/receipt fields
            $table->string('receipt_number', 50)->nullable()->unique()->after('quota_consumed');
            $table->decimal('total_price', 10, 2)->default(0)->after('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('lab_bookings', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_email', 'receipt_number', 'total_price']);
        });
    }
};
