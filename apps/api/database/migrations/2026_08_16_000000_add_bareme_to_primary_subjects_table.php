<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('primary_subjects', function (Blueprint $table): void {
            $table->decimal('bareme_cp1', 5, 2)->nullable()->after('coefficient_cm2');
            $table->decimal('bareme_cp2', 5, 2)->nullable()->after('bareme_cp1');
            $table->decimal('bareme_ce1', 5, 2)->nullable()->after('bareme_cp2');
            $table->decimal('bareme_ce2', 5, 2)->nullable()->after('bareme_ce1');
            $table->decimal('bareme_cm1', 5, 2)->nullable()->after('bareme_ce2');
            $table->decimal('bareme_cm2', 5, 2)->nullable()->after('bareme_cm1');
        });
    }

    public function down(): void
    {
        Schema::table('primary_subjects', function (Blueprint $table): void {
            $table->dropColumn(['bareme_cp1', 'bareme_cp2', 'bareme_ce1', 'bareme_ce2', 'bareme_cm1', 'bareme_cm2']);
        });
    }
};
