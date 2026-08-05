<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mitigation minimale malgré la stratégie Last-Write-Wins : conserve
     * l'ancienne et la nouvelle valeur écrasées pour investigation a
     * posteriori, même sans UI de résolution de conflit en Phase 3.
     */
    public function up(): void
    {
        Schema::create('sync_conflicts_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type');
            $table->uuid('entity_uuid');
            $table->json('previous_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamps();

            $table->index(['establishment_id', 'entity_type', 'entity_uuid'], 'sync_conflicts_log_est_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts_log');
    }
};
