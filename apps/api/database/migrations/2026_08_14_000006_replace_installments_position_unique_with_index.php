<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            // Le nouvel index doit exister avant de retirer l'unique : InnoDB s'en sert pour la FK establishment_id.
            $table->index(['establishment_id', 'school_year_id']);
            $table->dropUnique(['establishment_id', 'school_year_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            $table->unique(['establishment_id', 'school_year_id', 'position']);
            $table->dropIndex(['establishment_id', 'school_year_id']);
        });
    }
};
