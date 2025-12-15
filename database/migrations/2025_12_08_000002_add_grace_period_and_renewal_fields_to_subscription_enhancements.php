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
        Schema::table('subscription_enhancements', function (Blueprint $table) {
            // Grace period fields (add only if missing)
            if (!Schema::hasColumn('subscription_enhancements', 'grace_period_status')) {
                $table->enum('grace_period_status', ['none', 'active', 'expired'])->default('none')->index();
            }
            if (!Schema::hasColumn('subscription_enhancements', 'grace_period_starts_at')) {
                $table->timestamp('grace_period_starts_at')->nullable()->index();
            }
            if (!Schema::hasColumn('subscription_enhancements', 'grace_period_expires_at')) {
                $table->timestamp('grace_period_expires_at')->nullable()->index();
            }
            if (!Schema::hasColumn('subscription_enhancements', 'grace_period_reason')) {
                $table->string('grace_period_reason')->nullable();
            }

            // Auto-renewal tracking (add only if missing)
            if (!Schema::hasColumn('subscription_enhancements', 'renewal_attempt_count')) {
                $table->integer('renewal_attempt_count')->default(0);
            }
            if (!Schema::hasColumn('subscription_enhancements', 'last_renewal_attempt_at')) {
                $table->timestamp('last_renewal_attempt_at')->nullable();
            }
            if (!Schema::hasColumn('subscription_enhancements', 'last_renewal_result')) {
                $table->enum('last_renewal_result', ['success', 'pending', 'failed'])->nullable();
            }
            if (!Schema::hasColumn('subscription_enhancements', 'last_renewal_error')) {
                $table->string('last_renewal_error')->nullable();
            }
            if (!Schema::hasColumn('subscription_enhancements', 'next_auto_renewal_at')) {
                $table->timestamp('next_auto_renewal_at')->nullable()->index();
            }

            // Renewal preferences reference
            if (!Schema::hasColumn('subscription_enhancements', 'renewal_mode')) {
                $table->enum('renewal_mode', ['manual', 'automatic'])->default('manual')->index();
            }
            if (!Schema::hasColumn('subscription_enhancements', 'renewal_notes')) {
                $table->text('renewal_notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_enhancements', function (Blueprint $table) {
            $table->dropIndex(['grace_period_status']);
            $table->dropIndex(['grace_period_starts_at']);
            $table->dropIndex(['grace_period_expires_at']);
            $table->dropIndex(['last_renewal_attempt_at']);
            $table->dropIndex(['next_auto_renewal_at']);
            $table->dropIndex(['renewal_mode']);

            $table->dropColumn([
                'grace_period_status',
                'grace_period_starts_at',
                'grace_period_expires_at',
                'grace_period_reason',
                'renewal_attempt_count',
                'last_renewal_attempt_at',
                'last_renewal_result',
                'last_renewal_error',
                'next_auto_renewal_at',
                'renewal_mode',
                'renewal_notes',
            ]);
        });
    }
};
