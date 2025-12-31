<?php

use Illuminate\Support\Facades\DB;
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
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'user_id')) {
                // user_id should be nullable, with subscriber_id/subscriber_type being the primary reference
                $table->foreignId('user_id')->nullable()->after('status')->constrained('users')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'user_id')) {
            // Disable foreign key checks for SQLite compatibility
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF');
            }
            
            Schema::table('subscriptions', function (Blueprint $table) {
                // Try to drop foreign key if it exists
                try {
                    if (Schema::hasIndex('subscriptions', 'subscriptions_user_id_foreign')) {
                        $table->dropForeign('subscriptions_user_id_foreign');
                    }
                } catch (\Exception $e) {
                    // Silently ignore if foreign key doesn't exist
                }
                $table->dropColumn('user_id');
            });
            
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }
};
