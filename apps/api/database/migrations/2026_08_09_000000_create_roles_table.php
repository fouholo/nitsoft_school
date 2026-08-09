<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->string('code', 20)->primary();
            $table->string('wording', 50);
            $table->timestamps();
        });

        $now = now();

        DB::table('roles')->insert([
            ['code' => 'fondateur', 'wording' => 'Fondateur', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'directeur', 'wording' => 'Directeur', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'gestionnaire', 'wording' => 'Gestionnaire', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'enseignant', 'wording' => 'Enseignant', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'comptable', 'wording' => 'Comptable', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'parent', 'wording' => 'Parent', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
