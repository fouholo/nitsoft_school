<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_coefficients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('coefficient', 4, 2);

            $table->char('uid', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'level_id', 'serie_id', 'subject_id'], 'subject_coefficients_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_coefficients');
    }
};
