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
            $table->string('birth_place')->nullable()->after('gender');
            $table->string('nationalite_code', 5)->nullable()->after('birth_place');
            $table->string('birth_certificate_number')->nullable()->after('nationalite_code');
            $table->date('birth_certificate_date')->nullable()->after('birth_certificate_number');
            $table->string('birth_certificate_place')->nullable()->after('birth_certificate_date');
            $table->string('residence')->nullable()->after('birth_certificate_place');

            $table->foreign('nationalite_code')->references('code')->on('nationalites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign(['nationalite_code']);
            $table->dropColumn([
                'birth_place',
                'nationalite_code',
                'birth_certificate_number',
                'birth_certificate_date',
                'birth_certificate_place',
                'residence',
            ]);
        });
    }
};
