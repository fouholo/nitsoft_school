<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->foreignId('timetable_session_id')->nullable()->after('subject_id')->constrained()->nullOnDelete();
            $table->unique(['timetable_session_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table): void {
            $table->dropUnique(['timetable_session_id', 'session_date']);
            $table->dropConstrainedForeignId('timetable_session_id');
        });
    }
};
