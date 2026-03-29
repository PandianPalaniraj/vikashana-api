<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('invoice_no')->unique();
            $table->string('period_label');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('student_count');
            $table->decimal('rate_per_student', 8, 2);
            $table->string('billing_cycle', 20)->default('monthly');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('status', ['draft','sent','partial','paid','overdue','cancelled'])->default('draft');
            $table->date('due_date');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
