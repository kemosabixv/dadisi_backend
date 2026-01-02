<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds guest checkout fields, promo code, and discount tracking to event orders.
     */
    public function up(): void
    {
        Schema::table('event_orders', function (Blueprint $table) {
            // Guest checkout fields (when user_id is null)
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');

            // Promo code tracking
            $table->foreignId('promo_code_id')->nullable()->after('payment_id')
                ->constrained('promo_codes')->nullOnDelete();
            $table->decimal('promo_discount_amount', 12, 2)->default(0)->after('promo_code_id');

            // Subscriber discount tracking
            $table->decimal('subscriber_discount_amount', 12, 2)->default(0)->after('promo_discount_amount');
            $table->decimal('original_amount', 12, 2)->nullable()->after('subscriber_discount_amount');

            // Check-in tracking
            $table->string('qr_code_token')->nullable()->unique()->after('receipt_number');
            $table->timestamp('checked_in_at')->nullable()->after('qr_code_token');

            // Index for QR code lookups
            $table->index('qr_code_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_orders', function (Blueprint $table) {
            // Drop index safely
            if (Schema::hasIndex('event_orders', 'event_orders_qr_code_token_index')) {
                $table->dropIndex('event_orders_qr_code_token_index');
            }

            // Drop unique constraint safely
            if (Schema::hasIndex('event_orders', 'event_orders_qr_code_token_unique')) {
                $table->dropUnique('event_orders_qr_code_token_unique');
            }

            // Drop foreign key safely
            if (Schema::hasColumn('event_orders', 'promo_code_id')) {
                $table->dropForeign(['promo_code_id']);
            }

            // Drop columns that exist
            $columns = [
                'guest_name',
                'guest_email',
                'guest_phone',
                'promo_code_id',
                'promo_discount_amount',
                'subscriber_discount_amount',
                'original_amount',
                'qr_code_token',
                'checked_in_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('event_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
