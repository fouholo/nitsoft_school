<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_series', function (Blueprint $table): void {
            $table->id();
            $table->string('serie', 20)->unique();
            $table->string('serie_wording', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_series');
    }
};
