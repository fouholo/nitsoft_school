<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classes de la filière arabe (niveau arabe + série arabe éventuelle +
 * numéro), miroir de classrooms — voir
 * docs/superpowers/specs/2026-10-20-classes-arabes-design.md. Pas de
 * SoftDeletes (piège SoftDeletes + unicité) ; l'unicité est vérifiée par
 * l'écran, une contrainte SQL ne voyant pas les doublons « sans série »
 * (NULL n'est jamais égal à NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_classrooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_level_id')->constrained();
            $table->foreignId('arabic_serie_id')->nullable()->constrained();
            $table->string('numero', 2)->nullable();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();

            $table->index(['establishment_id', 'school_year_id'], 'arabic_classrooms_establishment_year_index');
        });

        DB::table('uid_server_counters')->insert([
            ['prefix' => '255', 'next_value' => 0],
        ]);
    }

    public function down(): void
    {
        DB::table('uid_server_counters')->where('prefix', '255')->delete();

        Schema::dropIfExists('arabic_classrooms');
    }
};
