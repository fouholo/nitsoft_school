<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->string('inspection_code', 10)->nullable();
            $table->foreign('inspection_code')->references('code')->on('inspections')->nullOnDelete();
            $table->string('opening_code')->nullable();
            $table->string('dsps_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_arabe')->default(false);
            $table->string('logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('establishments', function (Blueprint $table): void {
            $table->dropForeign(['inspection_code']);
            $table->dropColumn([
                'inspection_code',
                'opening_code',
                'dsps_code',
                'latitude',
                'longitude',
                'email',
                'is_arabe',
                'logo_path',
            ]);
        });
    }
};
