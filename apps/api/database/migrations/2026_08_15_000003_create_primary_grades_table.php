<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('primary_grades', function (Blueprint $table): void {
            $table->id();
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();

            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('primary_subject_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('comment')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['grade_sheet_id', 'student_id']);
            $table->index('establishment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('primary_grades');
    }
};
