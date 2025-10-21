<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('KES');
            $table->enum('status', ['pending','paid','cancelled','refunded'])->default('pending');
            $table->uuid('reference')->unique();
            $table->string('receipt_number', 50)->unique()->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_orders');
    }
};

