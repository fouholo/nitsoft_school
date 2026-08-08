<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_admins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main')->nullable();
            $table->unique('is_main');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_admins');
    }
};
