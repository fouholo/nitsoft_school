<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('level_fees', function (Blueprint $table): void {
            $table->decimal('registration_amount_assigned', 10, 2)->nullable()->after('registration_amount');
        });
    }

    public function down(): void
    {
        Schema::table('level_fees', function (Blueprint $table): void {
            $table->dropColumn('registration_amount_assigned');
        });
    }
};
