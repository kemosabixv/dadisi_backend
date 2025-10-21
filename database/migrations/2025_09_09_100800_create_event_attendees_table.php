<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('event_orders')->nullOnDelete();
            $table->string('name', 191);
            $table->string('email', 191);
            $table->boolean('checked_in')->default(false);
            $table->timestamp('check_in_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};

