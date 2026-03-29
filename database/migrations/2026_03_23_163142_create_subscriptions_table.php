<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->enum('plan', ['free', 'basic', 'pro', 'enterprise'])->default('free');
            $table->enum('status', ['trial', 'active', 'overdue', 'cancelled'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('renewal_date')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'annual'])->default('monthly');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
