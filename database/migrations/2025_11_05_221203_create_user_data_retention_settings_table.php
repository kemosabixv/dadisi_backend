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
        Schema::create('user_data_retention_settings', function (Blueprint $table) {
            $table->id();
            $table->string('data_type'); // 'user_accounts', 'audit_logs', 'backups', etc.
            $table->integer('retention_days'); // How many days to retain data
            $table->boolean('auto_delete')->default(true); // Whether to auto-delete
            $table->text('description')->nullable(); // Description of the setting
            $table->unsignedBigInteger('updated_by')->nullable(); // Who last updated this setting
            $table->timestamps();

            $table->unique('data_type');
            $table->index(['auto_delete', 'retention_days']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_data_retention_settings');
    }
};
