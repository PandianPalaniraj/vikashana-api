<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('admission_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('student_name');
            $table->date('dob')->nullable();
            $table->enum('gender', ['Male','Female','Other'])->nullable();
            $table->string('apply_class');
            $table->string('parent_name');
            $table->string('parent_phone', 20);
            $table->string('parent_email')->nullable();
            $table->text('address')->nullable();
            $table->enum('source', [
                'Walk-in','Phone Call','Website','WhatsApp',
                'Referral','Social Media','Newspaper Ad','Other'
            ])->default('Walk-in');
            $table->enum('stage', [
                'new','contacted','visit','docs','enrolled','rejected'
            ])->default('new');
            $table->text('notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('enquiry_date');
            $table->timestamps();

            $table->index(['school_id','stage']);
            $table->index(['school_id','enquiry_date']);
        });

        // When an enquiry converts to a real student record
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained('admission_enquiries')->nullOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('admission_no');
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->timestamp('admitted_at');
            $table->timestamps();

            $table->index('school_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('admission_enquiries');
    }
};
