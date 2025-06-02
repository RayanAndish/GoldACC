<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        // جدول admins
        DB::table('admins')->insert([
            ['name' => 'Admin One', 'email' => 'admin1@test.com', 'password' => bcrypt('password1'), 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Admin Two', 'email' => 'admin2@test.com', 'password' => bcrypt('password2'), 'role' => 'admin', 'created_at' => now(), 'updated_at' => now()]
        ]);

        // جدول customers
        DB::table('customers')->insert([
            ['name' => 'Customer One', 'email' => 'customer1@test.com', 'phone' => '09120000001', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Customer Two', 'email' => 'customer2@test.com', 'phone' => '09120000002', 'created_at' => now(), 'updated_at' => now()]
        ]);

        // جدول encryption_keys
        DB::table('encryption_keys')->insert([
            ['system_id' => 1, 'key_value' => 'test_key_123', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['system_id' => 2, 'key_value' => 'test_key_456', 'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()]
        ]);

        // جدول backups
        DB::table('backups')->insert([
            ['system_id' => 1, 'file_path' => '/backups/system1.bak', 'status' => 'pending', 'created_at' => now(), 'restored_at' => null],
            ['system_id' => 2, 'file_path' => '/backups/system2.bak', 'status' => 'completed', 'created_at' => now(), 'restored_at' => Carbon::now()->subDays(2)]
        ]);

        // جدول licenses
        DB::table('licenses')->insert([
            ['system_id' => 1, 'license_key' => 'ABC123XYZ', 'status' => 'active', 'expires_at' => Carbon::now()->addYear(), 'created_at' => now(), 'updated_at' => now()],
            ['system_id' => 2, 'license_key' => 'DEF456LMN', 'status' => 'expired', 'expires_at' => Carbon::now()->subMonth(), 'created_at' => now(), 'updated_at' => now()]
        ]);

        // جدول users
        DB::table('users')->insert([
            ['name' => 'User One', 'email' => 'user1@test.com', 'password' => bcrypt('password1'), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'User Two', 'email' => 'user2@test.com', 'password' => bcrypt('password2'), 'created_at' => now(), 'updated_at' => now()]
        ]);

        $this->command->info('Test data inserted successfully!');
    }
}
