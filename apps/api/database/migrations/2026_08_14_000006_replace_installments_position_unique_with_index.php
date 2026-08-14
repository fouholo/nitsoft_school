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
            $table->dropUnique(['establishment_id', 'school_year_id', 'position']);
            $table->index(['establishment_id', 'school_year_id']);
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            $table->dropIndex(['establishment_id', 'school_year_id']);
            $table->unique(['establishment_id', 'school_year_id', 'position']);
        });
    }
};
