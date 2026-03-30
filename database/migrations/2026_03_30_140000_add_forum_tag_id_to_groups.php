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
        Schema::table('groups', function (Blueprint $col) {
            $col->foreignId('forum_tag_id')->nullable()->after('county_id')->constrained('forum_tags')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $col) {
            $col->dropForeign(['forum_tag_id']);
            $col->dropColumn('forum_tag_id');
        });
    }
};
