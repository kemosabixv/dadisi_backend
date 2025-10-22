<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider', 50); // pesapal
            $table->string('event_type', 100);
            $table->string('external_id', 191)->index();
            $table->string('order_reference', 64)->index();
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->enum('status', ['received','processed','failed'])->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
