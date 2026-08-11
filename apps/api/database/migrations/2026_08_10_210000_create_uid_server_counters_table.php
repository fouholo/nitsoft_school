<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $prefixes = [
        '210', '211', '212', '213', '214', '215', '216',
        '220', '221', '222', '223',
        '230', '231', '232', '233', '234',
        '240', '241', '242', '243', '244', '245',
        '250',
    ];

    public function up(): void
    {
        Schema::create('uid_server_counters', function (Blueprint $table): void {
            $table->char('prefix', 3)->primary();
            $table->unsignedInteger('next_value')->default(0);
        });

        DB::table('uid_server_counters')->insert(
            array_map(static fn (string $prefix): array => ['prefix' => $prefix, 'next_value' => 0], $this->prefixes)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('uid_server_counters');
    }
};
