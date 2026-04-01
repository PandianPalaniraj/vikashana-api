<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\DB;

class SchoolDeletionService
{
    /**
     * Soft-delete the school and all related records.
     * Revokes all user tokens so active sessions are invalidated immediately.
     */
    public function softDelete(School $school): void
    {
        DB::transaction(function () use ($school) {
            // Revoke all tokens first so sessions are killed immediately
            $school->users()->each(fn ($u) => $u->tokens()->delete());

            // Soft-delete in dependency order (children before parents)
            $school->users()->withTrashed()->each(fn ($u) => $u->delete());

            // Students and their related records
            $school->students()->withTrashed()->each(function ($student) {
                $student->attendance()->delete();
                $student->feeInvoices()->each(function ($inv) {
                    $inv->payments()->delete();
                    $inv->delete();
                });
                $student->marks()->delete();
                $student->leaves()->delete();
                $student->delete();
            });

            // Teachers
            $school->teachers()->withTrashed()->each(fn ($t) => $t->delete());

            // Academic structure
            $school->timetables()->delete();
            $school->homework()->delete();
            $school->exams()->each(function ($exam) {
                $exam->subjects()->each(function ($es) {
                    $es->marks()->delete();
                    $es->delete();
                });
                $exam->delete();
            });
            $school->teacherAttendance()->delete();
            $school->feeStructures()->delete();
            $school->admissionEnquiries()->delete();
            $school->announcements()->delete();
            $school->broadcasts()->delete();
            $school->messageTemplates()->delete();
            $school->pushTokens()->delete();
            $school->activityLogs()->delete();
            $school->feedback()->delete();
            $school->quizQuestions()->delete();

            // Academic years, classes, sections, subjects
            $school->sections()->delete();
            $school->subjects()->delete();
            $school->classes()->delete();
            $school->academicYears()->delete();

            // Subscriptions
            $school->subscriptions()->each(function ($sub) {
                $sub->invoices()->each(function ($inv) {
                    $inv->payments()->delete();
                    $inv->delete();
                });
                $sub->delete();
            });

            // Finally soft-delete the school itself
            $school->delete();
        });
    }

    /**
     * Restore a soft-deleted school and all its related records.
     */
    public function restore(School $school): void
    {
        DB::transaction(function () use ($school) {
            $school->restore();

            $school->users()->withTrashed()->restore();
            $school->students()->withTrashed()->each(function ($student) {
                $student->restore();
                $student->attendance()->withTrashed()->restore();
                $student->feeInvoices()->withTrashed()->each(function ($inv) {
                    $inv->restore();
                    $inv->payments()->withTrashed()->restore();
                });
                $student->marks()->withTrashed()->restore();
                $student->leaves()->withTrashed()->restore();
            });
            $school->teachers()->withTrashed()->restore();
            $school->timetables()->withTrashed()->restore();
            $school->homework()->withTrashed()->restore();
            $school->exams()->withTrashed()->each(function ($exam) {
                $exam->restore();
                $exam->subjects()->withTrashed()->each(function ($es) {
                    $es->restore();
                    $es->marks()->withTrashed()->restore();
                });
            });
            $school->teacherAttendance()->withTrashed()->restore();
            $school->feeStructures()->withTrashed()->restore();
            $school->admissionEnquiries()->withTrashed()->restore();
            $school->announcements()->withTrashed()->restore();
            $school->broadcasts()->withTrashed()->restore();
            $school->messageTemplates()->withTrashed()->restore();
            $school->pushTokens()->withTrashed()->restore();
            $school->activityLogs()->withTrashed()->restore();
            $school->feedback()->withTrashed()->restore();
            $school->quizQuestions()->withTrashed()->restore();
            $school->sections()->withTrashed()->restore();
            $school->subjects()->withTrashed()->restore();
            $school->classes()->withTrashed()->restore();
            $school->academicYears()->withTrashed()->restore();
            $school->subscriptions()->withTrashed()->each(function ($sub) {
                $sub->restore();
                $sub->invoices()->withTrashed()->each(function ($inv) {
                    $inv->restore();
                    $inv->payments()->withTrashed()->restore();
                });
            });
        });
    }

    /**
     * Permanently delete a soft-deleted school and all its data.
     * School must already be soft-deleted before calling this.
     */
    public function hardDelete(School $school): void
    {
        DB::transaction(function () use ($school) {
            // Hard-delete in reverse dependency order
            $school->students()->withTrashed()->each(function ($student) {
                $student->attendance()->withTrashed()->forceDelete();
                $student->feeInvoices()->withTrashed()->each(function ($inv) {
                    $inv->payments()->withTrashed()->forceDelete();
                    $inv->forceDelete();
                });
                $student->marks()->withTrashed()->forceDelete();
                $student->leaves()->withTrashed()->forceDelete();
                $student->forceDelete();
            });

            $school->teachers()->withTrashed()->forceDelete();
            $school->users()->withTrashed()->forceDelete();
            $school->timetables()->withTrashed()->forceDelete();
            $school->homework()->withTrashed()->forceDelete();
            $school->exams()->withTrashed()->each(function ($exam) {
                $exam->subjects()->withTrashed()->each(function ($es) {
                    $es->marks()->withTrashed()->forceDelete();
                    $es->forceDelete();
                });
                $exam->forceDelete();
            });
            $school->teacherAttendance()->withTrashed()->forceDelete();
            $school->feeStructures()->withTrashed()->forceDelete();
            $school->admissionEnquiries()->withTrashed()->forceDelete();
            $school->announcements()->withTrashed()->forceDelete();
            $school->broadcasts()->withTrashed()->forceDelete();
            $school->messageTemplates()->withTrashed()->forceDelete();
            $school->pushTokens()->withTrashed()->forceDelete();
            $school->activityLogs()->withTrashed()->forceDelete();
            $school->feedback()->withTrashed()->forceDelete();
            $school->quizQuestions()->withTrashed()->forceDelete();
            $school->sections()->withTrashed()->forceDelete();
            $school->subjects()->withTrashed()->forceDelete();
            $school->classes()->withTrashed()->forceDelete();
            $school->academicYears()->withTrashed()->forceDelete();
            $school->subscriptions()->withTrashed()->each(function ($sub) {
                $sub->invoices()->withTrashed()->each(function ($inv) {
                    $inv->payments()->withTrashed()->forceDelete();
                    $inv->forceDelete();
                });
                $sub->forceDelete();
            });

            $school->forceDelete();
        });
    }
}
