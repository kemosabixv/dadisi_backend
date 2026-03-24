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
        // Use raw SQL to handle ENUM change as standard Blueprints can be tricky with ENUM updates
        // especially on different database engines (SQLite doesn't support ENUM, MySQL needs specific syntax)
        
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') DEFAULT 'pending'");
        } else {
            // For SQLite and others, we can't easily modify ENUM constraints, but they usually aren't as strict
            // or don't support ENUM at all (SQLite uses TEXT).
            Schema::table('payments', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Reverting might fail if there are records with 'cancelled' status
            // so we set them to 'failed' first
            DB::table('payments')->where('status', 'cancelled')->update(['status' => 'failed']);
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending'");
        } else {
            Schema::table('payments', function (Blueprint $table) {
                // Approximate reverting for non-mysql
                $table->string('status', 50)->default('pending')->change();
            });
        }
    }
};
