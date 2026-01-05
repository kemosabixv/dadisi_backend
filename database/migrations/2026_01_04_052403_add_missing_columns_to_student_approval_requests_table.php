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
            $table->string('student_email')->after('student_institution')->nullable();
            $table->date('student_birth_date')->after('student_email')->nullable();
            $table->string('county')->after('student_birth_date')->nullable();
            $table->text('additional_notes')->after('rejection_reason')->nullable();
            $table->renameColumn('requested_at', 'submitted_at');
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
