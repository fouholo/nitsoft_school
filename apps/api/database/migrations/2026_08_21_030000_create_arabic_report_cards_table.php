<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_report_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_term_id')->constrained()->cascadeOnDelete();
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->string('appreciation')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['enrollment_id', 'arabic_term_id'], 'arabic_report_cards_unique');
            $table->index(['establishment_id', 'arabic_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_report_cards');
    }
};
