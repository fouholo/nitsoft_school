<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compositions mensuelles du primaire arabe : matières par niveau propres à
 * l'école, notes sur 10 sans coefficient, résultats par classe arabe — voir
 * docs/superpowers/specs/2026-10-30-compositions-primaire-arabe-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_level_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->unique(['establishment_id', 'arabic_level_id', 'arabic_subject_id'], 'arabic_level_subjects_unique');
        });

        Schema::create('arabic_compositions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->date('graded_on');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['establishment_id', 'school_year_id', 'month'], 'arabic_compositions_month_unique');
        });

        Schema::create('arabic_composition_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_composition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 4, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->timestamps();

            $table->unique(['arabic_composition_id', 'enrollment_id', 'arabic_subject_id'], 'arabic_composition_grades_unique');
        });

        Schema::create('arabic_composition_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_composition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_classroom_id')->constrained()->cascadeOnDelete();
            $table->decimal('total', 6, 2)->nullable();
            $table->decimal('average', 4, 2)->nullable();
            $table->string('mention')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->text('appreciation')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['arabic_composition_id', 'enrollment_id'], 'arabic_composition_results_unique');
            $table->index(['arabic_composition_id', 'arabic_classroom_id'], 'arabic_composition_results_classroom_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_composition_results');
        Schema::dropIfExists('arabic_composition_grades');
        Schema::dropIfExists('arabic_compositions');
        Schema::dropIfExists('arabic_level_subjects');
    }
};
