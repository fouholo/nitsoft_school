<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue de réductions du fondateur — voir
 * docs/superpowers/specs/2026-10-31-catalogue-reductions-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->string('registration_type')->nullable();
            $table->decimal('registration_value', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['establishment_id', 'name']);
        });

        Schema::table('discounts', function (Blueprint $table): void {
            $table->foreignId('discount_preset_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('discount_preset_id');
        });

        Schema::dropIfExists('discount_presets');
    }
};
