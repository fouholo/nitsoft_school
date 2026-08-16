<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Le unique(['grade_sheet_id', 'student_id']) déclaré à la création de
        // la table existe sous SQLite (tests) mais pas sur cet environnement
        // WAMP/MySQL (moteur MyISAM, cf. investigation en session) — on tente
        // la suppression sans échouer si l'index est absent.
        try {
            Schema::table('primary_grades', function (Blueprint $table): void {
                $table->dropUnique(['grade_sheet_id', 'student_id']);
            });
        } catch (\Throwable) {
            // Index déjà absent (WAMP/MyISAM) — rien à faire.
        }

        Schema::table('primary_grades', function (Blueprint $table): void {
            $table->unique(['grade_sheet_id', 'student_id', 'primary_subject_id'], 'primary_grades_sheet_student_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::table('primary_grades', function (Blueprint $table): void {
            $table->dropUnique('primary_grades_sheet_student_subject_unique');
        });
    }
};
