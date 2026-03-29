<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('blood_group', 10)->nullable()->after('gender');
            $table->json('subjects_list')->nullable()->after('designation');
            $table->json('classes_list')->nullable()->after('subjects_list');
            $table->json('sections_list')->nullable()->after('classes_list');
            $table->json('docs')->nullable()->after('address');
        });

        // Extend photo to mediumtext for base64 storage
        DB::statement('ALTER TABLE teachers MODIFY photo MEDIUMTEXT NULL');

        // Add Resigned to status enum
        DB::statement("ALTER TABLE teachers MODIFY status ENUM('Active','Inactive','On Leave','Resigned') NOT NULL DEFAULT 'Active'");
    }

    public function down(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['blood_group','subjects_list','classes_list','sections_list','docs']);
        });
        DB::statement('ALTER TABLE teachers MODIFY photo VARCHAR(255) NULL');
        DB::statement("ALTER TABLE teachers MODIFY status ENUM('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active'");
    }
};
