<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. School ─────────────────────────────────────────
        $school = School::create([
            'name'    => 'Vidya Niketan School',
            'address' => 'Madurai, Tamil Nadu',
            'phone'   => '9876500000',
            'email'   => 'admin@vidyaniketan.edu.in',
            'website' => 'https://vidyaniketan.edu.in',
        ]);

        // ── 2. Admin user ─────────────────────────────────────
        User::create([
            'school_id' => $school->id,
            'name'      => 'Admin User',
            'email'     => 'admin@vidyaniketan.edu.in',
            'phone'     => '9876500000',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'status'    => 'active',
        ]);

        // ── 3. Academic Year ──────────────────────────────────
        $ay = AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2025-26',
            'start_date' => '2025-06-01',
            'end_date'   => '2026-03-31',
            'is_current' => true,
        ]);

        // ── 4. Classes & Sections ─────────────────────────────
        $classNames = ['Nursery','LKG','UKG','1','2','3','4','5','6','7','8','9','10','11','12'];
        foreach ($classNames as $order => $className) {
            $class = SchoolClass::create([
                'school_id'     => $school->id,
                'name'          => $className,
                'display_order' => $order,
            ]);
            foreach (['A','B','C'] as $sectionName) {
                Section::create([
                    'school_id' => $school->id,
                    'class_id'  => $class->id,
                    'name'      => $sectionName,
                    'capacity'  => 40,
                ]);
            }
        }

        $this->command->info('✅ Seed complete — login: admin@vidyaniketan.edu.in / password');
    }
}
