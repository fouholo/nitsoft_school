<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('primary_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->string('name');
            $table->string('abbreviation', 10);
            $table->decimal('coefficient_cp1', 4, 2)->nullable();
            $table->decimal('coefficient_cp2', 4, 2)->nullable();
            $table->decimal('coefficient_ce1', 4, 2)->nullable();
            $table->decimal('coefficient_ce2', 4, 2)->nullable();
            $table->decimal('coefficient_cm1', 4, 2)->nullable();
            $table->decimal('coefficient_cm2', 4, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('primary_subjects');
    }
};
