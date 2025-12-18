<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_type', 100)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('disk', 50)->default('public');
            $table->string('file_path', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
            $table->index(['owner_type','owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};

