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
        'students',
        'enrollments',
        'grade_sheets',
        'grades',
        'attendance_sessions',
        'attendance_records',
        'invoices',
        'payments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('uuid', 'uid');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->char('uid', 12)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('uid')->change();
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->renameColumn('uid', 'uuid');
            });
        }
    }
};
