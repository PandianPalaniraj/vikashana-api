<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First migrate any 'free' plan rows to 'starter'
        DB::statement("UPDATE subscriptions SET plan='starter', status='trial', trial_ends_at=DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE plan='free'");

        DB::statement("ALTER TABLE subscriptions
            MODIFY COLUMN plan ENUM('starter','pro','premium','enterprise') NOT NULL DEFAULT 'pro'");

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'grace_period_ends_at')) {
                $table->date('grace_period_ends_at')->nullable()->after('paid_until');
            }
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions
            MODIFY COLUMN plan ENUM('free','starter','pro','premium','enterprise') NOT NULL DEFAULT 'pro'");

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('grace_period_ends_at');
        });
    }
};
