<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('school_code', 20)->nullable()->unique()->after('name');
        });

        // Back-fill existing schools
        DB::table('schools')->get(['id', 'name'])->each(function ($school) {
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $school->name), 0, 3));
            if (strlen($prefix) < 2) $prefix = 'SCH';
            $code = $prefix . rand(1000, 9999);
            while (DB::table('schools')->where('school_code', $code)->exists()) {
                $code = $prefix . rand(1000, 9999);
            }
            DB::table('schools')->where('id', $school->id)->update(['school_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique(['school_code']);
            $table->dropColumn('school_code');
        });
    }
};
