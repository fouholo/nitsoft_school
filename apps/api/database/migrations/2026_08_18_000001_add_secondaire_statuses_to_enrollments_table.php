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
            $table->boolean('is_repeating')->default(false);
            $table->boolean('is_scholarship')->default(false);
            $table->boolean('is_boarding')->default(false);
            $table->boolean('is_assigned')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn(['is_repeating', 'is_scholarship', 'is_boarding', 'is_assigned']);
        });
    }
};
