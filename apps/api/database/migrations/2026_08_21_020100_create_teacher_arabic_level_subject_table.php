<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_arabic_level_subject');
    }
};
