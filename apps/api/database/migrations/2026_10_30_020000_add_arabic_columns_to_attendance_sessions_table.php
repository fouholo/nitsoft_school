<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un appel porte sur une classe française ou sur une classe arabe — voir
 * docs/superpowers/specs/2026-10-30-appel-classes-arabes-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('classroom_id')->nullable()->change();
            $table->foreignId('arabic_classroom_id')->nullable()->after('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            $table->foreignId('arabic_timetable_session_id')->nullable()->after('timetable_session_id')->constrained()->nullOnDelete();

            $table->unique(['arabic_timetable_session_id', 'session_date'], 'attendance_sessions_arabic_session_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            // Clé étrangère d'abord : sur MySQL, elle s'appuie sur l'index unique.
            $table->dropForeign(['arabic_timetable_session_id']);
            $table->dropUnique('attendance_sessions_arabic_session_date_unique');
            $table->dropColumn('arabic_timetable_session_id');
            $table->dropConstrainedForeignId('arabic_subject_id');
            $table->dropConstrainedForeignId('arabic_classroom_id');
            $table->unsignedBigInteger('classroom_id')->nullable(false)->change();
        });
    }
};
