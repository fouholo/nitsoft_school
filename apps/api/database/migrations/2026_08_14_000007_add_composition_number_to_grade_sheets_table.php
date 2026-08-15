<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_sheets', function (Blueprint $table): void {
            $table->foreignId('term_id')->nullable()->change();
            $table->unsignedTinyInteger('composition_number')->nullable()->after('term_id');
        });
    }

    public function down(): void
    {
        Schema::table('grade_sheets', function (Blueprint $table): void {
            $table->dropColumn('composition_number');
            $table->foreignId('term_id')->nullable(false)->change();
        });
    }
};
