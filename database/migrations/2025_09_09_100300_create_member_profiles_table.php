<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('phone', 30)->nullable();
            $table->foreignId('county_id')->nullable()->constrained('counties')->nullOnDelete();
            $table->json('interests')->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_profiles');
    }
};

