<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // login account
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('admission_no')->unique();
            $table->string('name');
            $table->date('dob')->nullable();
            $table->enum('gender', ['Male','Female','Other'])->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('photo')->nullable();          // relative path
            $table->text('address')->nullable();
            $table->string('religion')->nullable();
            $table->string('nationality')->default('Indian');
            $table->enum('status', ['Active','Inactive','Transferred','Graduated'])
                  ->default('Active');
            $table->date('admission_date')->nullable();
            $table->timestamps();

            $table->index(['school_id','class_id','section_id']);
            $table->index(['school_id','status']);
        });

        Schema::create('student_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('relation', ['Father','Mother','Guardian','Other'])->default('Father');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();
            $table->string('annual_income')->nullable();
            $table->boolean('is_primary')->default(false);   // primary contact
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // parent login
            $table->timestamps();

            $table->index('student_id');
        });

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // "Birth Certificate"
            $table->string('path');                       // file path
            $table->string('type', 50)->nullable();       // pdf/image
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_parents');
        Schema::dropIfExists('students');
    }
};
