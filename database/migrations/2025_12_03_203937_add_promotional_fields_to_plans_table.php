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
        Schema::table('plans', function (Blueprint $table) {
            // Monthly promotion fields already exist, so skip them

            // Add the missing yearly promotion fields
            $table->decimal('yearly_promotion_discount_percent', 5, 2)->default(0)->after('yearly_discount_percent');
            $table->timestamp('yearly_promotion_expires_at')->nullable()->after('yearly_promotion_discount_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'yearly_promotion_discount_percent',
                'yearly_promotion_expires_at'
            ]);

            // Note: Monthly promotion fields will remain as they were added elsewhere
        });
    }
};
