<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('payable_type', 100);
            $table->unsignedBigInteger('payable_id');
            $table->string('gateway', 50)->default('pesapal');
            $table->string('method', 50)->nullable();
            $table->enum('status', ['pending','paid','failed','refunded'])->default('pending');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('KES');
            $table->string('external_reference', 191)->nullable()->index();
            $table->string('order_reference', 64)->unique();
            $table->string('receipt_url', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['payable_type','payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

