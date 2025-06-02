<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SystemSeeder extends Seeder
{
    public function run()
    {
        DB::table('systems')->insert([
            ['id' => 1, 'customer_id' => 1, 'name' => 'System One', 'domain' => 'system1.com', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'customer_id' => 2, 'name' => 'System Two', 'domain' => 'system2.com', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        ]);

        $this->command->info('Systems table seeded successfully!');
    }
}
