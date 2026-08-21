<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->foreignId('arabic_level_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
            $table->foreignId('arabic_serie_id')->nullable()->after('arabic_level_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('arabic_level_id');
            $table->dropConstrainedForeignId('arabic_serie_id');
        });
    }
};
