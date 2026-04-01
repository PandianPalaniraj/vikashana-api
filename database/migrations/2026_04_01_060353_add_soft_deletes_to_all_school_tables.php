<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tables that need deleted_at added (schools already has it)
    private array $tables = [
        'users',
        'students',
        'teachers',
        'classes',
        'sections',
        'subjects',
        'academic_years',
        'timetables',
        'attendance',
        'teacher_attendance',
        'fee_invoices',
        'fee_payments',
        'fee_structures',
        'homework',
        'exams',
        'exam_subjects',
        'marks',
        'admission_enquiries',
        'announcements',
        'broadcasts',
        'message_templates',
        'subscriptions',
        'subscription_invoices',
        'subscription_payments',
        'push_tokens',
        'activity_logs',
        'feedback',
        'student_leaves',
        'quiz_questions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            }
        }
    }
};
