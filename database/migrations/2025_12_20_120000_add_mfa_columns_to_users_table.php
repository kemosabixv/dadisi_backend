<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds two-factor authentication columns to users table.
     * Note: webauthn_credentials table is created by laragear/webauthn package.
     */
    public function up(): void
    {
        // Add TOTP 2FA columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });

        // Note: WebAuthn credentials table is managed by laragear/webauthn package migration
        // Do NOT create webauthn_credentials here to avoid conflict
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: webauthn_credentials is managed by laragear/webauthn package
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_enabled',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
