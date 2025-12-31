<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('payments', 'payer_id')) {
                $table->unsignedBigInteger('payer_id')->after('payable_id')->nullable();
                $table->foreign('payer_id')->references('id')->on('users')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('payments', 'transaction_id')) {
                $table->string('transaction_id', 191)->after('external_reference')->nullable()->index();
            }
            
            if (!Schema::hasColumn('payments', 'payment_method')) {
                $table->string('payment_method', 50)->after('method')->nullable();
            }
            
            if (!Schema::hasColumn('payments', 'description')) {
                $table->text('description')->nullable();
            }
            
            if (!Schema::hasColumn('payments', 'reference')) {
                $table->string('reference', 191)->nullable()->index();
            }
            
            if (!Schema::hasColumn('payments', 'county')) {
                $table->string('county', 100)->nullable();
            }
            
            if (!Schema::hasColumn('payments', 'metadata')) {
                $table->json('metadata')->nullable();
            }
            
            if (!Schema::hasColumn('payments', 'pesapal_order_id')) {
                $table->string('pesapal_order_id', 191)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeignIdFor('payer_id', 'payments_payer_id_foreign');
            $table->dropIndexIfExists('payments_payer_id_foreign');
            $table->dropColumn([
                'payer_id',
                'transaction_id',
                'payment_method',
                'description',
                'reference',
                'county',
                'metadata',
                'pesapal_order_id',
            ]);
        });
    }
};
