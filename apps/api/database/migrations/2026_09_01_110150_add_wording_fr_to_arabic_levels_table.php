<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arabic_levels', function (Blueprint $table): void {
            $table->string('wording_fr', 100)->nullable()->after('wording');
        });
    }

    public function down(): void
    {
        Schema::table('arabic_levels', function (Blueprint $table): void {
            $table->dropColumn('wording_fr');
        });
    }
};
