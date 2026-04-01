<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Super Admin ────────────────────────────────────────
        $this->call(SuperAdminSeeder::class);

        // ── 2. Demo School ────────────────────────────────────────
        $school = School::create([
            'name'    => 'Vikashana Demo School',
            'address' => 'Madurai, Tamil Nadu',
            'phone'   => '9876500000',
            'email'   => 'admin@demo.vikashana.com',
            'website' => 'https://vikashana.com',
        ]);

        // ── 3. School Admin ───────────────────────────────────────
        User::create([
            'school_id' => $school->id,
            'name'      => 'School Admin',
            'email'     => 'admin@demo.vikashana.com',
            'phone'     => '9876500000',
            'password'  => Hash::make('Admin@123'),
            'role'      => 'admin',
            'status'    => 'active',
        ]);

        // ── 4. Academic Year ──────────────────────────────────────
        AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2025-26',
            'start_date' => '2025-06-01',
            'end_date'   => '2026-03-31',
            'is_current' => true,
        ]);

        // ── 5. Classes, Sections & Subjects ──────────────────────
        $curriculum = [
            ['name' => 'Nursery', 'subjects' => []],
            ['name' => 'LKG',     'subjects' => []],
            ['name' => 'UKG',     'subjects' => []],
            ['name' => '1',  'subjects' => ['English', 'Tamil', 'Maths', 'EVS']],
            ['name' => '2',  'subjects' => ['English', 'Tamil', 'Maths', 'EVS']],
            ['name' => '3',  'subjects' => ['English', 'Tamil', 'Maths', 'Science', 'Social Science']],
            ['name' => '4',  'subjects' => ['English', 'Tamil', 'Maths', 'Science', 'Social Science']],
            ['name' => '5',  'subjects' => ['English', 'Tamil', 'Maths', 'Science', 'Social Science']],
            ['name' => '6',  'subjects' => ['English', 'Tamil', 'Maths', 'Science', 'Social Science']],
            ['name' => '7',  'subjects' => ['English', 'Tamil', 'Maths', 'Science', 'Social Science']],
            ['name' => '8',  'subjects' => ['English', 'Tamil', 'Maths', 'Science', 'Social Science']],
            ['name' => '9',  'subjects' => ['English', 'Tamil', 'Maths', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography']],
            ['name' => '10', 'subjects' => ['English', 'Tamil', 'Maths', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography']],
            ['name' => '11', 'subjects' => ['English', 'Tamil', 'Maths', 'Physics', 'Chemistry', 'Biology', 'Computer Science']],
            ['name' => '12', 'subjects' => ['English', 'Tamil', 'Maths', 'Physics', 'Chemistry', 'Biology', 'Computer Science']],
        ];

        foreach ($curriculum as $order => $item) {
            $class = SchoolClass::create([
                'school_id'     => $school->id,
                'name'          => $item['name'],
                'display_order' => $order,
            ]);

            foreach (['A', 'B', 'C'] as $sectionName) {
                Section::create([
                    'school_id' => $school->id,
                    'class_id'  => $class->id,
                    'name'      => $sectionName,
                    'capacity'  => 40,
                ]);
            }

            foreach ($item['subjects'] as $subjectName) {
                Subject::create([
                    'school_id'   => $school->id,
                    'class_id'    => $class->id,
                    'name'        => $subjectName,
                    'code'        => strtoupper(substr($subjectName, 0, 3)) . $class->id,
                    'is_optional' => false,
                ]);
            }
        }

        // ── 6. Quiz Questions ─────────────────────────────────────
        $this->call(QuizSeeder::class);

        $this->command->info('');
        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('');
        $this->command->info('  Super Admin:');
        $this->command->info('    Email:    superadmin@vikashana.com');
        $this->command->info('    Password: Vikashana@2026');
        $this->command->info('');
        $this->command->info('  School Admin (Demo School):');
        $this->command->info('    Email:    admin@demo.vikashana.com');
        $this->command->info('    Password: Admin@123');
        $this->command->info('');
    }
}
