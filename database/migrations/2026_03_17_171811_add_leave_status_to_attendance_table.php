<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendance MODIFY status ENUM('Present','Absent','Late','Half Day','Leave') NOT NULL DEFAULT 'Present'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendance MODIFY status ENUM('Present','Absent','Late','Half Day') NOT NULL DEFAULT 'Present'");
    }
};
