<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_student', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('is_primary_contact');
            $table->string('relationship')->nullable()->after('status');
            $table->index(['establishment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('guardian_student', function (Blueprint $table): void {
            $table->dropIndex(['establishment_id', 'status']);
            $table->dropColumn(['status', 'relationship']);
        });
    }
};
