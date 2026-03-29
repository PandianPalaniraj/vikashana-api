<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('homework', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('assigned_date');
            $table->date('due_date');
            $table->json('attachments')->nullable();      // [{name, path, size}]
            $table->enum('status', ['Active','Completed','Cancelled'])->default('Active');
            $table->timestamps();

            $table->index(['school_id','class_id','section_id']);
            $table->index(['teacher_id','due_date']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // "Unit Test 1", "Half Yearly"
            $table->enum('type', ['Unit Test','Mid Term','Final','Board','Internal','Other'])
                  ->default('Unit Test');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['Upcoming','Ongoing','Completed'])->default('Upcoming');
            $table->timestamps();

            $table->index(['school_id','class_id','academic_year_id']);
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->time('start_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->decimal('max_marks', 6, 2)->default(100);
            $table->decimal('pass_marks', 6, 2)->default(35);
            $table->string('venue')->nullable();
            $table->timestamps();

            $table->unique(['exam_id','subject_id']);
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->string('grade', 5)->nullable();       // A+, A, B, etc.
            $table->enum('result', ['Pass','Fail','Absent','Withheld'])->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_subject_id','student_id']);
            $table->index(['exam_id','student_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('marks');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('homework');
    }
};
