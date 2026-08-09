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
            $table->boolean('is_general_admin')->nullable();
            $table->boolean('is_local_admin')->nullable();
            $table->unique(['establishment_id', 'is_general_admin'], 'establishment_user_general_admin_unique');
            $table->unique(['establishment_id', 'is_local_admin'], 'establishment_user_local_admin_unique');
        });

        Schema::table('foundation_user', function (Blueprint $table): void {
            $table->boolean('is_general_admin')->nullable();
            $table->unique(['foundation_id', 'is_general_admin'], 'foundation_user_general_admin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('establishment_user', function (Blueprint $table): void {
            $table->dropUnique('establishment_user_general_admin_unique');
            $table->dropUnique('establishment_user_local_admin_unique');
            $table->dropColumn(['is_general_admin', 'is_local_admin']);
        });

        Schema::table('foundation_user', function (Blueprint $table): void {
            $table->dropUnique('foundation_user_general_admin_unique');
            $table->dropColumn('is_general_admin');
        });
    }
};
