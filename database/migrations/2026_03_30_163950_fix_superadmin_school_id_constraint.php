<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing foreign key constraint
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
        });

        // Make school_id truly nullable
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('school_id')->nullable()->change();
        });

        // Re-add foreign key with nullOnDelete so superadmin can have school_id = null
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('school_id')
                  ->references('id')
                  ->on('schools')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->foreign('school_id')
                  ->references('id')
                  ->on('schools')
                  ->onDelete('cascade');
        });
    }
};
