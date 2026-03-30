<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->unsignedBigInteger('school_id')->nullable()->change();
            $table->foreign('school_id')
                  ->references('id')
                  ->on('schools')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
            $table->foreign('school_id')
                  ->references('id')
                  ->on('schools')
                  ->cascadeOnDelete();
        });
    }
};
