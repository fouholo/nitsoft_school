<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La table receipts est fusionnée dans payments : le numéro de reçu devient
 * uid_local (avant synchronisation) puis uid_serveur (après) du paiement
 * lui-même, déjà présents via Syncable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('receipts');
    }

    public function down(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number');
            $table->string('pdf_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['establishment_id', 'receipt_number']);
        });
    }
};
