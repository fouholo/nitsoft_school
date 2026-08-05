<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('student_number');
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);

            // Colonnes de synchronisation offline-first (utilisées à partir de la
            // Phase 3, posées dès maintenant pour éviter une migration de
            // rattrapage en production — voir plan d'architecture, section 5.4).
            $table->uuid('uuid')->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['establishment_id', 'student_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
