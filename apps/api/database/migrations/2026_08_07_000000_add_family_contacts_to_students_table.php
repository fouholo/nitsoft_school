<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('father_name')->nullable()->after('gender');
            $table->string('father_phone')->nullable()->after('father_name');
            $table->string('mother_name')->nullable()->after('father_phone');
            $table->string('mother_phone')->nullable()->after('mother_name');
            $table->string('tutor_name')->nullable()->after('mother_phone');
            $table->string('tutor_phone')->nullable()->after('tutor_name');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn([
                'father_name',
                'father_phone',
                'mother_name',
                'mother_phone',
                'tutor_name',
                'tutor_phone',
            ]);
        });
    }
};
