<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week');
            $table->foreignId('timetable_slot_id')->constrained()->cascadeOnDelete();
            $table->string('room')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'classroom_id']);
            $table->index(['establishment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_sessions');
    }
};
