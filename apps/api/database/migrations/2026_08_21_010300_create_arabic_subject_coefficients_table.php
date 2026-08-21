<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_subject_coefficients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_serie_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('coefficient', 4, 2);

            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'arabic_level_id', 'arabic_serie_id', 'arabic_subject_id'], 'arabic_subject_coefficients_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_subject_coefficients');
    }
};
