<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foundation_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('foundation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // fondateur (FK vers roles.code, ajoutée par une migration ultérieure)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['foundation_id', 'user_id', 'role']);
            $table->index(['foundation_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foundation_user');
    }
};
