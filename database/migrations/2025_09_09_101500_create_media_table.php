<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_type', 100);
            $table->unsignedBigInteger('owner_id');
            $table->string('disk', 50)->default('public');
            $table->string('path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
            $table->index(['owner_type','owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};

