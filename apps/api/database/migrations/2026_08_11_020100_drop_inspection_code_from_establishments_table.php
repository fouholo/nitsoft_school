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
            $table->dropForeign(['inspection_code']);
            $table->dropColumn('inspection_code');
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->string('inspection_code', 10)->nullable();
            $table->foreign('inspection_code')->references('code')->on('inspections')->nullOnDelete();
        });
    }
};
