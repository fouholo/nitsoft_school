<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('uid_counters');
    }

    public function down(): void
    {
        Schema::create('uid_counters', function (Blueprint $table): void {
            $table->id();
        });
    }
};
