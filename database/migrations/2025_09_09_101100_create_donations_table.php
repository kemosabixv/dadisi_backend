<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('donor_name', 191);
            $table->string('donor_email', 191);
            $table->string('donor_phone', 30)->nullable();
            $table->foreignId('county_id')->nullable()->constrained('counties')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('KES');
            $table->enum('status', ['pending','paid','failed','refunded'])->default('pending');
            $table->uuid('reference')->unique();
            $table->string('receipt_number', 50)->unique()->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};

