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
            $table->foreignId('subject_id')->nullable()->change();
            $table->foreignId('primary_subject_id')->nullable()->after('subject_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grade_sheets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_subject_id');
            $table->foreignId('subject_id')->nullable(false)->change();
        });
    }
};
