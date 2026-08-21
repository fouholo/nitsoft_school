<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('sequence');
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['establishment_id', 'school_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_terms');
    }
};
