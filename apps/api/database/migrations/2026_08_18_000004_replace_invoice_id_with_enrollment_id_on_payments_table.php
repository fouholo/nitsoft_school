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
            $table->foreignId('enrollment_id')->after('student_id')->constrained()->cascadeOnDelete();
            $table->index(['establishment_id', 'enrollment_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['establishment_id', 'invoice_id']);
            $table->dropConstrainedForeignId('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->after('establishment_id')->constrained()->cascadeOnDelete();
            $table->index(['establishment_id', 'invoice_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['establishment_id', 'enrollment_id']);
            $table->dropConstrainedForeignId('enrollment_id');
        });
    }
};
