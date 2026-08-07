<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->dropForeign(['establishment_id']);
            $table->dropIndex(['establishment_id']);
            $table->dropColumn(['establishment_id', 'relationship']);
        });
    }

    public function down(): void
    {
        Schema::table('guardians', function (Blueprint $table): void {
            $table->foreignId('establishment_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->nullable()->after('email');
            $table->index('establishment_id');
        });
    }
};
