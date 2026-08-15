<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('uid_server_counters')->insert([
            ['prefix' => '225', 'next_value' => 0],
        ]);
    }

    public function down(): void
    {
        DB::table('uid_server_counters')->where('prefix', '225')->delete();
    }
};
