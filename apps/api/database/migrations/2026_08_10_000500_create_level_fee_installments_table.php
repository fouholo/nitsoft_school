<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_fee_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('level_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['level_fee_id', 'installment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_fee_installments');
    }
};
