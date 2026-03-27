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
        Schema::table('student_approval_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('student_approval_requests', 'student_email')) {
                $table->string('student_email')->after('student_institution')->nullable();
            }
            if (!Schema::hasColumn('student_approval_requests', 'student_birth_date')) {
                $table->date('student_birth_date')->after('student_email')->nullable();
            }
            if (!Schema::hasColumn('student_approval_requests', 'county')) {
                $table->string('county')->after('student_birth_date')->nullable();
            }
            if (!Schema::hasColumn('student_approval_requests', 'additional_notes')) {
                $table->text('additional_notes')->after('rejection_reason')->nullable();
            }
            // Note: column remains 'requested_at' (not renamed to submitted_at)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_approval_requests', function (Blueprint $table) {
            $table->renameColumn('submitted_at', 'requested_at');
            $table->dropColumn(['student_email', 'student_birth_date', 'county', 'additional_notes']);
        });
    }
};
