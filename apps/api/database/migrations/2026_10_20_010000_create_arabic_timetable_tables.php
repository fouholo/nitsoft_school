<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emploi du temps des classes arabes : grille de créneaux propre à l'école,
 * jours de cours arabes choisis par l'école, séances par classe arabe —
 * miroir des tables françaises. Voir
 * docs/superpowers/specs/2026-10-20-emploi-du-temps-arabe-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->json('arabic_timetable_days')->nullable();
        });

        Schema::create('arabic_timetable_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('sequence');
            $table->boolean('is_break')->default(false);
            $table->timestamps();

            $table->unique(['establishment_id', 'sequence'], 'arabic_timetable_slots_establishment_sequence_unique');
        });

        Schema::create('arabic_timetable_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week', 20);
            $table->foreignId('arabic_timetable_slot_id')->constrained()->cascadeOnDelete();
            $table->string('room')->nullable();
            $table->timestamps();

            $table->index(['establishment_id', 'day_of_week'], 'arabic_timetable_sessions_establishment_day_index');
            $table->index(['establishment_id', 'user_id'], 'arabic_timetable_sessions_establishment_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_timetable_sessions');
        Schema::dropIfExists('arabic_timetable_slots');

        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropColumn('arabic_timetable_days');
        });
    }
};
