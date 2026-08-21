<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('wording', 100);
            $table->string('cycle');
            $table->boolean('requires_series')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_levels');
    }
};
