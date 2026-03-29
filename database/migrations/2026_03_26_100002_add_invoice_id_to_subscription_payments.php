<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('subscription_id')
                ->constrained('subscription_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\SubscriptionInvoice::class);
            $table->dropColumn('invoice_id');
        });
    }
};
