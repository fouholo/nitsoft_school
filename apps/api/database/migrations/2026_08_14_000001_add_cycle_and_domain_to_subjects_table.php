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
            $table->boolean('is_prescolaire_primaire')->default(true)->after('name');
            $table->boolean('is_secondaire')->default(true)->after('is_prescolaire_primaire');
            $table->foreignId('domain_id')->nullable()->after('is_secondaire')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('domain_id');
            $table->dropColumn(['is_prescolaire_primaire', 'is_secondaire']);
        });
    }
};
