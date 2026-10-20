<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Affectations d'enseignants et grilles de notes arabes regroupées par
 * classe arabe au lieu du couple niveau/série — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 *
 * Les trois tables sont recréées plutôt que modifiées : elles doivent être
 * vides (vérifié avant toute opération), et sur MySQL la clé étrangère
 * user_id de teacher_arabic_level_subject n'a pas d'index propre (elle
 * s'appuie sur l'index unique à remplacer), ce qui rendrait une
 * modification en place fragile.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['teacher_arabic_level_subject', 'arabic_grade_sheets', 'arabic_grades'] as $table) {
            $count = DB::table($table)->count();

            if ($count > 0) {
                throw new RuntimeException(
                    "Migration interrompue : la table {$table} contient {$count} ligne(s). "
                    .'Le regroupement arabe par niveau/série ne peut pas être converti automatiquement en classes arabes.'
                );
            }
        }

        Schema::drop('arabic_grades');
        Schema::drop('arabic_grade_sheets');
        Schema::drop('teacher_arabic_level_subject');

        Schema::create('teacher_arabic_classroom_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'arabic_classroom_id', 'arabic_subject_id', 'school_year_id'], 'teacher_arabic_classroom_subject_unique');
            $table->index(['establishment_id', 'arabic_classroom_id'], 'teacher_arabic_classroom_establishment_index');
        });

        Schema::create('arabic_grade_sheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->decimal('max_score', 6, 2);
            $table->decimal('weight', 4, 2);
            $table->date('graded_on');
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'arabic_classroom_id'], 'arabic_grade_sheets_establishment_classroom_index');
        });

        $this->createArabicGradesTable();
    }

    public function down(): void
    {
        Schema::drop('arabic_grades');
        Schema::drop('arabic_grade_sheets');
        Schema::drop('teacher_arabic_classroom_subject');

        Schema::create('teacher_arabic_level_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_serie_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'arabic_level_id', 'arabic_serie_id', 'arabic_subject_id', 'school_year_id'], 'teacher_arabic_level_subject_unique');
            $table->index(['establishment_id', 'arabic_level_id'], 'teacher_arabic_level_subject_establishment_level_index');
        });

        Schema::create('arabic_grade_sheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_serie_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->decimal('max_score', 6, 2);
            $table->decimal('weight', 4, 2);
            $table->date('graded_on');
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'arabic_level_id', 'arabic_serie_id'], 'arabic_grade_sheets_establishment_level_serie_index');
        });

        $this->createArabicGradesTable();
    }

    private function createArabicGradesTable(): void
    {
        Schema::create('arabic_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_grade_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['arabic_grade_sheet_id', 'enrollment_id']);
        });
    }
};
