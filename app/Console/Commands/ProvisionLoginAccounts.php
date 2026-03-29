<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProvisionLoginAccounts extends Command
{
    protected $signature   = 'provision:login-accounts {--school= : Limit to a specific school_id} {--dry-run : Preview without saving}';
    protected $description = 'Create login accounts for teachers and student parents that have none';

    public function handle(): int
    {
        $schoolId = $this->option('school') ?: null;
        $dryRun   = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN — no changes will be saved');
        }

        $this->line('');
        $this->provisionTeachers($schoolId, $dryRun);
        $this->line('');
        $this->provisionStudentParents($schoolId, $dryRun);
        $this->line('');
        $this->info('✅ Done.');

        return 0;
    }

    // ── Teachers ──────────────────────────────────────────────────────────────

    private function provisionTeachers(?int $schoolId, bool $dryRun): void
    {
        $this->info('👨‍🏫  TEACHERS');

        $query = Teacher::whereNull('user_id');
        if ($schoolId) $query->where('school_id', $schoolId);
        $teachers = $query->get();

        if ($teachers->isEmpty()) {
            $this->line('   All teachers already have login accounts.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Phone', 'Login (phone)', 'Password'],
            $teachers->map(fn($t) => [
                $t->id,
                $t->name,
                $t->phone ?: '(none)',
                $t->phone ?: '(will be auto-generated)',
                $this->teacherPassword($t),
            ])
        );

        if ($dryRun) return;

        $created = 0;
        foreach ($teachers as $t) {
            DB::transaction(function () use ($t, &$created) {
                $phone = $t->phone ?: ('T' . str_pad($t->id, 9, '0', STR_PAD_LEFT));

                // Skip if a user with this phone already exists for this school
                $existing = User::where('phone', $phone)
                    ->where('school_id', $t->school_id)
                    ->where('role', 'teacher')
                    ->first();

                if ($existing) {
                    $t->update(['user_id' => $existing->id]);
                    $this->line("   ↩ Linked existing user for: {$t->name}");
                    return;
                }

                $password = $this->teacherPassword($t);

                $user = User::create([
                    'school_id' => $t->school_id,
                    'name'      => $t->name,
                    'email'     => $t->email ?? null,
                    'phone'     => $phone,
                    'password'  => $password,
                    'role'      => 'teacher',
                    'status'    => 'active',
                ]);

                $t->update(['user_id' => $user->id]);
                $created++;
            });
        }

        $this->info("   ✅ Created {$created} teacher account(s).");
    }

    // ── Student parents ───────────────────────────────────────────────────────

    private function provisionStudentParents(?int $schoolId, bool $dryRun): void
    {
        $this->info('👨‍👩‍👧  STUDENT PARENTS');

        // Students whose primary parent has no user_id (or no parent at all)
        $studentQuery = Student::with(['parents' => fn($q) => $q->where('is_primary', true)]);
        if ($schoolId) $studentQuery->where('school_id', $schoolId);

        $students = $studentQuery->get()->filter(function ($student) {
            $primary = $student->parents->first();
            if (!$primary) return true;           // no parent record
            return is_null($primary->user_id);    // parent record but no user
        });

        if ($students->isEmpty()) {
            $this->line('   All students already have parent login accounts.');
            return;
        }

        $this->line("   Found {$students->count()} student(s) needing parent accounts.");

        if ($dryRun) {
            $this->table(
                ['Student ID', 'Name', 'Admission No', 'DOB', 'Parent Phone', 'Password'],
                $students->take(20)->map(fn($s) => [
                    $s->id,
                    $s->name,
                    $s->admission_no,
                    $s->dob?->format('d-m-Y') ?? '—',
                    $this->parentPhone($s),
                    $this->parentPassword($s),
                ])
            );
            if ($students->count() > 20) $this->line('   … and ' . ($students->count() - 20) . ' more');
            return;
        }

        $created = 0;
        $index   = 0;

        foreach ($students as $student) {
            DB::transaction(function () use ($student, &$created, &$index) {
                $phone    = $this->parentPhone($student, $index);
                $password = $this->parentPassword($student);
                $index++;

                // Reuse existing user with this phone (any role) for this school
                $existing = User::where('phone', $phone)
                    ->where('school_id', $student->school_id)
                    ->first();

                if ($existing) {
                    // Ensure it's treated as a parent
                    if ($existing->role !== 'parent') {
                        $existing->update(['role' => 'parent']);
                    }
                    $parentUser = $existing;
                } else {
                    $parentUser = User::create([
                        'school_id' => $student->school_id,
                        'name'      => 'Parent of ' . $student->name,
                        'phone'     => $phone,
                        'password'  => $password,
                        'role'      => 'parent',
                        'status'    => 'active',
                    ]);
                    $created++;
                }

                // Find existing primary parent record or create one
                $parentRecord = $student->parents->first();

                if ($parentRecord) {
                    $parentRecord->update(['user_id' => $parentUser->id]);
                } else {
                    StudentParent::create([
                        'student_id' => $student->id,
                        'user_id'    => $parentUser->id,
                        'name'       => 'Parent of ' . $student->name,
                        'phone'      => $phone,
                        'relation'   => 'Father',
                        'is_primary' => true,
                    ]);
                }

            });
        }

        $this->info("   ✅ Created {$created} parent account(s) for {$students->count()} student(s).");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function teacherPassword(Teacher $t): string
    {
        if ($t->dob) {
            return Carbon::parse($t->dob)->format('dmY');
        }
        return 'Teacher@123';
    }

    private function parentPhone(Student $s, int $index = 0): string
    {
        // Use existing parent phone if available
        $existing = $s->parents->first();
        if ($existing && $existing->phone) return $existing->phone;

        // Generate sequential phone: 70000 + school_id (2 digits) + student_id (5 digits)
        return '7' . str_pad($s->school_id, 2, '0', STR_PAD_LEFT) . str_pad($s->id, 7, '0', STR_PAD_LEFT);
    }

    private function parentPassword(Student $s): string
    {
        if ($s->dob) {
            return $s->dob->format('dmY');
        }
        return 'Parent@123';
    }
}
