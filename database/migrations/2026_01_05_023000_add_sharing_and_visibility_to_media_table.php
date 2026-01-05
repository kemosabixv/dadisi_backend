<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('visibility')->default('private');
        });

        Schema::table('media', function (Blueprint $table) {
            $table->uuid('share_token')->nullable()->unique();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->boolean('allow_download')->default(true);
        });

        // Data migration: mapping is_public to visibility
        // If is_public exists (it should, based on Media model), migrate the value
        if (Schema::hasColumn('media', 'is_public')) {
            DB::table('media')->where('is_public', true)->update(['visibility' => 'public']);
            DB::table('media')->where('is_public', false)->update(['visibility' => 'private']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'share_token', 'allow_download']);
        });
    }
};
