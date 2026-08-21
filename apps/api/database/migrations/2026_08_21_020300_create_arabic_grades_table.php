<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arabic_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('arabic_grade_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->string('uid_local', 20)->unique();
            $table->char('uid_serveur', 12)->nullable()->unique();
            $table->uuid('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['arabic_grade_sheet_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arabic_grades');
    }
};
