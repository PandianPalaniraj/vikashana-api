<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_id')->nullable();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('photo')->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['Male','Female','Other'])->nullable();
            $table->string('designation')->nullable();    // "HOD", "PGT", "TGT"
            $table->string('department')->nullable();     // "Science", "Maths"
            $table->string('qualification')->nullable();
            $table->date('joining_date')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['Active','Inactive','On Leave'])->default('Active');
            $table->timestamps();

            $table->unique(['school_id','employee_id']);
            $table->index(['school_id','status']);
        });

        // Which teacher teaches which subject in which class/section
        Schema::create('teacher_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teacher_id','subject_id','class_id','section_id','academic_year_id'],
                           'teacher_subject_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('teacher_subjects');
        Schema::dropIfExists('teachers');
    }
};
