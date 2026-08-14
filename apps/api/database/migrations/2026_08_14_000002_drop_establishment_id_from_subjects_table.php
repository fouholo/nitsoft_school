<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropIndex(['establishment_id']);
            $table->dropConstrainedForeignId('establishment_id');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->foreignId('establishment_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index('establishment_id');
        });
    }
};
