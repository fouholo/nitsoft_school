<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('uid_server_counters')->insert([
            ['prefix' => '252', 'next_value' => 0],
            ['prefix' => '253', 'next_value' => 0],
            ['prefix' => '254', 'next_value' => 0],
        ]);
    }

    public function down(): void
    {
        DB::table('uid_server_counters')->whereIn('prefix', ['252', '253', '254'])->delete();
    }
};
