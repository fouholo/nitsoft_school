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
            $table->string('matricule')->nullable()->after('role');
            $table->string('job_title')->nullable()->after('matricule');
            $table->date('hired_at')->nullable()->after('job_title');
            $table->string('education_level')->nullable()->after('hired_at');
        });
    }

    public function down(): void
    {
        Schema::table('establishment_user', function (Blueprint $table): void {
            $table->dropColumn(['matricule', 'job_title', 'hired_at', 'education_level']);
        });
    }
};
