<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classe principale / classe secondaire : la classe française devient
 * facultative (un élève dont la classe principale est arabe peut ne pas en
 * avoir), la classe arabe remplace le couple niveau/série arabe porté par
 * l'inscription — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        $withArabicGroup = DB::table('enrollments')
            ->whereNotNull('arabic_level_id')
            ->orWhereNotNull('arabic_serie_id')
            ->count();

        if ($withArabicGroup > 0) {
            throw new RuntimeException(
                "Migration interrompue : {$withArabicGroup} inscription(s) portent encore un niveau ou une série arabe. "
                .'Placez ces élèves dans une classe arabe avant de remplacer ces colonnes.'
            );
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('arabic_level_id');
            $table->dropConstrainedForeignId('arabic_serie_id');
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->unsignedBigInteger('classroom_id')->nullable()->change();
            $table->foreignId('arabic_classroom_id')->nullable()->after('classroom_id')->constrained()->restrictOnDelete();
            $table->string('main_track', 20)->default('francais')->after('arabic_classroom_id');
            $table->boolean('is_arabic_repeating')->default(false)->after('is_repeating');

            $table->index(['establishment_id', 'arabic_classroom_id'], 'enrollments_establishment_arabic_classroom_index');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex('enrollments_establishment_arabic_classroom_index');
            $table->dropConstrainedForeignId('arabic_classroom_id');
            $table->dropColumn(['main_track', 'is_arabic_repeating']);
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->unsignedBigInteger('classroom_id')->nullable(false)->change();
            $table->foreignId('arabic_level_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
            $table->foreignId('arabic_serie_id')->nullable()->after('arabic_level_id')->constrained()->nullOnDelete();
        });
    }
};
