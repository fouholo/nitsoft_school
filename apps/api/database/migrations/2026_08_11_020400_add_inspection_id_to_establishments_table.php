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
            $table->foreignId('inspection_id')->nullable()->after('foundation_id')->constrained('inspections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropForeign(['inspection_id']);
            $table->dropColumn('inspection_id');
        });
    }
};
