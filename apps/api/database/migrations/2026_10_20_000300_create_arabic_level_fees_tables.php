<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grille de frais par niveau arabe, miroir de level_fees /
 * level_fee_installments sans montant « affecté » (l'affectation par l'État
 * ne concerne que le secondaire français) — utilisée quand la classe
 * principale de l'élève est arabe. Voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_level_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_level_id')->constrained();
            $table->decimal('registration_amount', 12, 2)->default(0);
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['establishment_id', 'school_year_id', 'arabic_level_id'], 'arabic_level_fees_establishment_year_level_unique');
        });

        Schema::create('arabic_level_fee_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('arabic_level_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['arabic_level_fee_id', 'installment_id'], 'arabic_level_fee_installments_fee_installment_unique');
        });

        DB::table('uid_server_counters')->insert([
            ['prefix' => '256', 'next_value' => 0],
        ]);
    }

    public function down(): void
    {
        DB::table('uid_server_counters')->where('prefix', '256')->delete();

        Schema::dropIfExists('arabic_level_fee_installments');
        Schema::dropIfExists('arabic_level_fees');
    }
};
