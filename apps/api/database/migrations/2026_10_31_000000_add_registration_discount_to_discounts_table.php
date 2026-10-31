<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une réduction porte sur la scolarité (type, value), sur les frais
 * d'inscription (registration_type, registration_value), ou sur les deux —
 * voir docs/superpowers/specs/2026-10-31-reductions-droits-inscription-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            $table->string('type')->nullable()->change();
            $table->decimal('value', 12, 2)->nullable()->change();
            $table->string('registration_type')->nullable()->after('value');
            $table->decimal('registration_value', 12, 2)->nullable()->after('registration_type');
        });
    }

    public function down(): void
    {
        // Une réduction sur les seuls frais d'inscription n'existe pas dans
        // l'ancien schéma.
        DB::table('discounts')->whereNull('type')->delete();

        Schema::table('discounts', function (Blueprint $table): void {
            $table->dropColumn(['registration_type', 'registration_value']);
            $table->string('type')->nullable(false)->change();
            $table->decimal('value', 12, 2)->nullable(false)->change();
        });
    }
};
