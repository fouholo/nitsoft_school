<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_information', function (Blueprint $table): void {
            $table->string('armoirie_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('general_information', function (Blueprint $table): void {
            $table->dropColumn('armoirie_path');
        });
    }
};
