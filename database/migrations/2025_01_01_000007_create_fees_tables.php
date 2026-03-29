<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained()->nullOnDelete(); // null = all classes
            $table->string('name');                       // "Term 1 Fees", "Annual Fees"
            $table->json('items');                        // [{label, amount}]
            $table->decimal('total', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['school_id','academic_year_id']);
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no')->unique();
            $table->string('month', 20)->nullable();      // "January 2026" or null for annual
            $table->json('items');                        // [{label, amount}]
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('paid', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->enum('status', ['Unpaid','Partial','Paid'])->default('Unpaid');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('wa_sent')->default(false);
            $table->boolean('receipt_sent')->default(false);
            $table->timestamps();

            $table->index(['school_id','student_id']);
            $table->index(['school_id','status']);
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['Cash','Online','Cheque','DD','UPI'])->default('Cash');
            $table->string('reference')->nullable();      // cheque no / transaction id
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('fee_structures');
    }
};
