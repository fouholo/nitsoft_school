<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèle d'en-tête des documents PDF, officiel par défaut — voir
 * docs/superpowers/specs/2026-10-31-choix-entete-documents-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->string('pdf_header_style')->default('official')->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropColumn('pdf_header_style');
        });
    }
};
