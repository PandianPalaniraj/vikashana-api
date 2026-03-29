<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('audience', ['all','students','staff','parents','class','section'])
                  ->default('all');
            $table->foreignId('class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id','audience']);
            $table->index(['school_id','is_pinned']);
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('audience_type');              // "all_students","all_staff","class","section"
            $table->string('audience_label');             // human readable: "Class 10", "All Staff"
            $table->text('message');
            $table->integer('reach')->default(0);         // number of recipients
            $table->enum('status', ['sent','failed','pending'])->default('sent');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['school_id','sent_at']);
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category');                   // "Finance","Academic","General" etc.
            $table->text('body');
            $table->json('tags')->nullable();             // ["fee","reminder"]
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id','category']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('announcements');
    }
};
