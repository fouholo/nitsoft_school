<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('registration_paid', 12, 2)->nullable()->after('reference');
            $table->decimal('registration_remaining', 12, 2)->nullable()->after('registration_paid');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['registration_paid', 'registration_remaining']);
        });
    }
};
