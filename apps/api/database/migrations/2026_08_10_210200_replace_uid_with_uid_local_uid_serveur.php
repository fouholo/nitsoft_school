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
        'subject_coefficients',
        'users',
        'students',
        'guardians',
        'enrollments',
        'grade_sheets',
        'grades',
        'attendance_sessions',
        'attendance_records',
        'invoices',
        'payments',
        'discounts',
        'installments',
        'level_fees',
        'expenses',
        'sms_templates',
    ];

    /**
     * Tables Phase 0 dont la colonne a été renommée uuid -> uid
     * (2026_08_06_020000) : l'index unique a gardé son nom interne
     * "{table}_uuid_unique", jamais renommé (déjà documenté comme
     * cosmétique dans le design du 2026-08-06).
     *
     * @var list<string>
     */
    private array $tablesWithStaleUuidIndexName = [
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
            $indexColumn = in_array($table, $this->tablesWithStaleUuidIndexName, true) ? 'uuid' : 'uid';

            Schema::table($table, function (Blueprint $blueprint) use ($indexColumn): void {
                $blueprint->dropUnique([$indexColumn]);
                $blueprint->dropColumn('uid');
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('uid_local', 20)->unique();
                $blueprint->char('uid_serveur', 12)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['uid_local', 'uid_serveur']);
            });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->char('uid', 12)->nullable()->unique();
            });
        }
    }
};
