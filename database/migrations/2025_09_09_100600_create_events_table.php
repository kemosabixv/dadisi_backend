<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->longText('description');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('venue', 191)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_link', 255)->nullable();
            $table->integer('capacity')->nullable();
            $table->foreignId('county_id')->nullable()->constrained('counties')->nullOnDelete();
            $table->string('image_path', 255)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('KES');
            $table->enum('status', ['draft','published'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop foreign key constraints from dependent tables if they exist
        if (Schema::hasTable('event_rsvps')) {
            Schema::table('event_rsvps', function (Blueprint $table) {
                $table->dropForeign(['event_id']);
            });
        }

        if (Schema::hasTable('event_attendees')) {
            Schema::table('event_attendees', function (Blueprint $table) {
                $table->dropForeign(['event_id']);
            });
        }

        if (Schema::hasTable('event_orders')) {
            Schema::table('event_orders', function (Blueprint $table) {
                $table->dropForeign(['event_id']);
            });
        }

        Schema::dropIfExists('events');
    }
};
