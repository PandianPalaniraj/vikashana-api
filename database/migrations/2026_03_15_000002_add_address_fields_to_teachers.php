<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('city',    80)->nullable()->after('address');
            $table->string('state',   80)->nullable()->after('city');
            $table->string('pincode', 10)->nullable()->after('state');
        });
    }

    public function down(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['city','state','pincode']);
        });
    }
};
