<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_cards', function (Blueprint $table): void {
            $table->foreignId('term_id')->nullable()->change();
            $table->foreignId('school_year_id')->nullable()->after('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('composition_number')->nullable()->after('school_year_id');

            $table->unique(['student_id', 'school_year_id', 'composition_number']);
        });
    }

    public function down(): void
    {
        Schema::table('report_cards', function (Blueprint $table): void {
            $table->dropUnique(['student_id', 'school_year_id', 'composition_number']);
            $table->dropConstrainedForeignId('school_year_id');
            $table->dropColumn('composition_number');
            $table->foreignId('term_id')->nullable(false)->change();
        });
    }
};
