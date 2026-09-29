<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes d'inscription d'un fondateur qui crée lui-même son école (ou son
 * groupe scolaire + première école). Rien n'est créé dans users/
 * establishments/foundations avant validation par un administrateur SaaS :
 * une ligne n'existe que tant que la demande est en attente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('first_name');
            $table->string('email')->unique();
            $table->string('pseudo')->unique();
            $table->string('password');
            $table->string('establishment_name');
            $table->string('establishment_type');
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('direction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('foundation_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_registrations');
    }
};
