<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishment_user', function (Blueprint $table): void {
            $table->foreign('role')->references('code')->on('roles');
        });

        Schema::table('foundation_user', function (Blueprint $table): void {
            $table->foreign('role')->references('code')->on('roles');
        });
    }

    public function down(): void
    {
        Schema::table('establishment_user', function (Blueprint $table): void {
            $table->dropForeign(['role']);
        });

        Schema::table('foundation_user', function (Blueprint $table): void {
            $table->dropForeign(['role']);
        });
    }
};
