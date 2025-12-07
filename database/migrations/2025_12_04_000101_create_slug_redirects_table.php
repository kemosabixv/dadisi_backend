<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('old_slug', 191)->unique();
            $table->string('new_slug', 191);
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['old_slug', 'new_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
