<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained('event_categories')->onDelete('set null');
            $table->foreignId('organizer_id')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
            $table->timestamp('registration_deadline')->nullable()->after('ends_at');
            $table->boolean('waitlist_enabled')->default(false)->after('capacity');
            $table->integer('waitlist_capacity')->nullable()->after('waitlist_enabled');
            $table->boolean('featured')->default(false)->after('status');
            $table->timestamp('featured_until')->nullable()->after('featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('organizer_id');
            $table->dropColumn([
                'registration_deadline',
                'waitlist_enabled',
                'waitlist_capacity',
                'featured',
                'featured_until'
            ]);
        });
    }
};
