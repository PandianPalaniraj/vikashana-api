<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Delete existing superadmin if exists
        DB::table('users')
            ->where('role', 'super_admin')
            ->delete();

        // Create fresh superadmin
        DB::table('users')->insert([
            'school_id'         => null,
            'name'              => 'Super Admin',
            'email'             => 'superadmin@vikashana.com',
            'phone'             => '9999999999',
            'password'          => Hash::make('Vikashana@2026'),
            'role'              => 'super_admin',
            'avatar'            => null,
            'status'            => 'active',
            'last_login'        => null,
            'settings'          => null,
            'remember_token'    => null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $this->command->info('Super admin created successfully!');
        $this->command->info('Email:    superadmin@vikashana.com');
        $this->command->info('Password: Vikashana@2026');
    }
}
