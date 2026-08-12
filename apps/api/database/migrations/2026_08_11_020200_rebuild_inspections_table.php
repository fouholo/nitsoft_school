<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('inspections');

        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->string('codeiep', 6)->unique();
            $table->string('inspection_name', 50);
            $table->string('address', 50)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('location', 50)->nullable();
            $table->char('uid_direction', 12)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');

        Schema::create('inspections', function (Blueprint $table): void {
            $table->string('code', 10)->primary();
            $table->string('libelle', 100);
            $table->timestamps();
        });
    }
};
