<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            // La FK doit tomber avant l'index : InnoDB refuse de supprimer un index qu'une contrainte utilise.
            $table->dropForeign(['establishment_id']);
            $table->dropIndex(['establishment_id']);
            $table->dropColumn('establishment_id');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->foreignId('establishment_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index('establishment_id');
        });
    }
};
