<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('primary_subjects', function (Blueprint $table): void {
            $table->foreignId('subject_id')->after('id')->constrained()->cascadeOnDelete();
            $table->unique('subject_id');
            $table->dropColumn(['name', 'abbreviation']);
        });
    }

    public function down(): void
    {
        Schema::table('primary_subjects', function (Blueprint $table): void {
            $table->dropUnique(['subject_id']);
            $table->dropConstrainedForeignId('subject_id');
            $table->string('name')->nullable();
            $table->string('abbreviation', 10)->nullable();
        });
    }
};
