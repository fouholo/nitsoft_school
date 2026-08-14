<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_classroom_subject', function (Blueprint $table): void {
            $table->foreignId('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_classroom_subject', function (Blueprint $table): void {
            $table->foreignId('subject_id')->nullable(false)->change();
        });
    }
};
