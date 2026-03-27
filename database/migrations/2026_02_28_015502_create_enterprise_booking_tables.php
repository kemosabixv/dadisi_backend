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
        // 1. Booking Series (for recurring and flexible sets)
        if (! Schema::hasTable('booking_series')) {
            Schema::create('booking_series', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->foreignId('lab_space_id')->constrained('lab_spaces')->onDelete('cascade');
                $table->string('reference')->unique();
                $table->enum('type', ['single', 'recurring', 'flexible'])->default('single');
                $table->decimal('total_hours', 8, 2);
                $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
                $table->json('metadata')->nullable(); // Store recurrence rules or flexible requirements
                $table->timestamps();
                $table->softDeletes();

                $table->index('status');
                $table->index('reference');
            });
        }

        // 2. Slot Holds (ephemeral locks for two-phase booking)
        if (! Schema::hasTable('slot_holds')) {
            Schema::create('slot_holds', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->index();
                $table->foreignId('lab_space_id')->constrained('lab_spaces')->onDelete('cascade');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->dateTime('expires_at')->index();
                $table->string('payment_intent_id')->nullable()->index();
                $table->integer('renewal_count')->default(0);
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('guest_email')->nullable();
                $table->foreignId('series_id')->nullable()->constrained('booking_series')->onDelete('cascade');
                $table->timestamps();

                $table->index(['lab_space_id', 'starts_at', 'ends_at']);
            });
        }

        // 3. Processed Webhooks (idempotency for PesaPal)
        if (! Schema::hasTable('processed_webhooks')) {
            Schema::create('processed_webhooks', function (Blueprint $table) {
                $table->id();
                $table->string('provider')->default('pesapal');
                $table->string('webhook_id')->unique();
                $table->json('payload')->nullable();
                $table->dateTime('processed_at');
                $table->timestamps();
            });
        }

        // 4. Booking Audit Logs
        if (! Schema::hasTable('booking_audit_logs')) {
            Schema::create('booking_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_id')->nullable()->index();
                $table->unsignedBigInteger('series_id')->nullable()->index();
                $table->string('action'); // initiated, held, confirmed, cancelled, attended, no-show
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // performer
                $table->text('notes')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        // 5. Lab Closures (Holidays/Maintenance)
        if (! Schema::hasTable('lab_closures')) {
            Schema::create('lab_closures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('lab_space_id')->nullable()->constrained('lab_spaces')->onDelete('cascade'); // Null means all labs
                $table->date('start_date');
                $table->date('end_date');
                $table->string('reason');
                $table->timestamps();

                $table->index(['start_date', 'end_date']);
            });
        }

        // 6. Quota Commitments (for yearly subscribers)
        if (! Schema::hasTable('quota_commitments')) {
            Schema::create('quota_commitments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->date('month_date'); // first day of the month
                $table->decimal('committed_hours', 8, 2);
                $table->foreignId('series_id')->constrained('booking_series')->onDelete('cascade');
                $table->timestamps();

                $table->unique(['user_id', 'month_date', 'series_id'], 'user_month_series_unique');
            });
        }

        // 7. Update Lab Bookings table
        Schema::table('lab_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_bookings', 'booking_series_id')) {
                $table->foreignId('booking_series_id')->nullable()->after('id')->constrained('booking_series')->onDelete('set null');
            }
            if (! Schema::hasColumn('lab_bookings', 'payment_id')) {
                $table->foreignId('payment_id')->nullable()->after('booking_series_id')->constrained('payments')->onDelete('set null');
            }
            if (! Schema::hasColumn('lab_bookings', 'booking_reference')) {
                $table->string('booking_reference')->nullable()->after('payment_id')->index();
            }
            if (! Schema::hasColumn('lab_bookings', 'payment_method')) {
                $table->enum('payment_method', ['quota', 'card', 'mpesa', 'mixed'])->nullable()->after('status');
            }
            if (! Schema::hasColumn('lab_bookings', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('payment_method');
            }
            if (! Schema::hasColumn('lab_bookings', 'quota_hours')) {
                $table->decimal('quota_hours', 8, 2)->default(0)->after('paid_amount');
            }

            // For Guest Bookings (if not already present or needs verification)
            if (! Schema::hasColumn('lab_bookings', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('lab_bookings', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_bookings', function (Blueprint $table) {
            $table->dropForeign(['booking_series_id']);
            $table->dropForeign(['payment_id']);
            $table->dropColumn(['booking_series_id', 'payment_id', 'booking_reference', 'payment_method', 'paid_amount', 'quota_hours']);
        });

        Schema::dropIfExists('quota_commitments');
        Schema::dropIfExists('lab_closures');
        Schema::dropIfExists('booking_audit_logs');
        Schema::dropIfExists('processed_webhooks');
        Schema::dropIfExists('slot_holds');
        Schema::dropIfExists('booking_series');
    }
};
