<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->foreignId('direction_id')->nullable()->after('inspection_id')->constrained('directions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropForeign(['direction_id']);
            $table->dropColumn('direction_id');
        });
    }
};
