<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add new columns
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('student_count')->default(0)->after('billing_cycle');
            $table->decimal('amount_per_student', 8, 2)->default(0)->after('student_count');
            $table->decimal('monthly_amount', 10, 2)->default(0)->after('amount_per_student');
            $table->boolean('mobile_enabled')->default(false)->after('monthly_amount');
            $table->json('features')->nullable()->after('mobile_enabled');
            $table->date('paid_until')->nullable()->after('features');
        });

        // Step 2: Update plan enum to include new plans
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('free','starter','pro','premium','enterprise') NOT NULL DEFAULT 'free'");

        // Step 3: Update status enum to include 'expired'
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('trial','active','overdue','cancelled','expired') NOT NULL DEFAULT 'trial'");

        // Step 4: Migrate old 'basic' plan to 'starter'
        DB::statement("UPDATE subscriptions SET plan = 'starter' WHERE plan = 'basic'");

        // Step 5: Set mobile_enabled for pro/premium/enterprise
        DB::statement("UPDATE subscriptions SET mobile_enabled = 1 WHERE plan IN ('pro','premium','enterprise')");

        // Step 6: Sync student_count from actual students table
        DB::statement("
            UPDATE subscriptions s
            JOIN (SELECT school_id, COUNT(*) as cnt FROM students WHERE status = 'Active' GROUP BY school_id) sc
            ON s.school_id = sc.school_id
            SET s.student_count = sc.cnt
        ");

        // Step 7: Set monthly_amount based on plan and student_count
        DB::statement("UPDATE subscriptions SET amount_per_student = 15, monthly_amount = student_count * 15 WHERE plan = 'starter'");
        DB::statement("UPDATE subscriptions SET amount_per_student = 25, monthly_amount = student_count * 25 WHERE plan = 'pro'");
        DB::statement("UPDATE subscriptions SET amount_per_student = 40, monthly_amount = student_count * 40 WHERE plan = 'premium'");
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['student_count', 'amount_per_student', 'monthly_amount', 'mobile_enabled', 'features', 'paid_until']);
        });

        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN plan ENUM('free','basic','pro','enterprise') NOT NULL DEFAULT 'free'");
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('trial','active','overdue','cancelled') NOT NULL DEFAULT 'trial'");
    }
};
