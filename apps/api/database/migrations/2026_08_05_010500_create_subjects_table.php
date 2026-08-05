<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('coefficient_default', 4, 2)->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('establishment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
