<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('email', 191);
            $table->enum('status', ['confirmed','cancelled','waitlisted'])->default('confirmed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rsvps');
    }
};

