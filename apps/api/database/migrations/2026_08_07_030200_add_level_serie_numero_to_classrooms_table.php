<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->foreignId('level_id')->after('school_year_id')->constrained('levels');
            $table->foreignId('serie_id')->nullable()->after('level_id')->constrained('series')->nullOnDelete();
            $table->string('numero', 2)->after('serie_id');
            $table->dropColumn(['level', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('level_id');
            $table->dropConstrainedForeignId('serie_id');
            $table->dropColumn('numero');
            $table->string('level')->nullable();
            $table->string('cycle')->default('secondaire');
        });
    }
};
