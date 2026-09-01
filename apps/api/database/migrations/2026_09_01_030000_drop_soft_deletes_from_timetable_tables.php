<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TimetableSlot/TimetableSession ne sont pas des enregistrements
 * historiques (comme TeacherAssignment) : le soft delete combiné à
 * l'index unique (establishment_id, sequence) de timetable_slots
 * bloquait indéfiniment la réutilisation d'un numéro de créneau après
 * suppression — voir SlotsIndex::save() et la mémoire projet associée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('timetable_sessions', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('timetable_sessions', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }
};
