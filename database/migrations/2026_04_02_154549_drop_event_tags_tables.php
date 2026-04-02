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
        Schema::dropIfExists('event_tag');
        Schema::dropIfExists('event_tags');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy reversal for dropped tables with data
    }
};
