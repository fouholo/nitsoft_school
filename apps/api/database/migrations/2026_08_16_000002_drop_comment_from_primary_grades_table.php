<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('primary_grades', function (Blueprint $table): void {
            $table->dropColumn('comment');
        });
    }

    public function down(): void
    {
        Schema::table('primary_grades', function (Blueprint $table): void {
            $table->string('comment')->nullable();
        });
    }
};
