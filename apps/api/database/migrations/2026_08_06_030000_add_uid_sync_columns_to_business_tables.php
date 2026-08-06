<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'establishments',
        'foundations',
        'school_years',
        'terms',
        'classrooms',
        'subjects',
        'guardians',
        'fee_schedules',
        'sms_templates',
        'users',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->char('uid', 12)->nullable()->unique();
                $blueprint->uuid('device_id')->nullable();
                $blueprint->timestamp('client_updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['uid', 'device_id', 'client_updated_at']);
            });
        }
    }
};
