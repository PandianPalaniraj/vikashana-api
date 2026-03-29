<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['Present','Absent','Late','Half Day'])->default('Present');
            $table->text('note')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // one record per student per day
            $table->unique(['student_id','date'], 'student_date_unique');
            $table->index(['school_id','date']);
            $table->index(['class_id','section_id','date']);
        });

        Schema::create('teacher_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['Present','Absent','Late','On Leave','Half Day'])->default('Present');
            $table->text('note')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id','date'], 'teacher_date_unique');
            $table->index(['school_id','date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('teacher_attendance');
        Schema::dropIfExists('attendance');
    }
};
