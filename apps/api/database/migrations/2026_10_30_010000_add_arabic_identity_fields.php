<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Champs facultatifs du bulletin du secondaire arabe — voir
 * docs/superpowers/specs/2026-10-30-bulletin-secondaire-arabe-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('arabic_name')->nullable()->after('last_name');
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('city')->nullable()->after('name_ar');
        });

        Schema::table('arabic_terms', function (Blueprint $table): void {
            $table->string('label_ar')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('arabic_terms', function (Blueprint $table): void {
            $table->dropColumn('label_ar');
        });

        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropColumn(['name_ar', 'city']);
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('arabic_name');
        });
    }
};
