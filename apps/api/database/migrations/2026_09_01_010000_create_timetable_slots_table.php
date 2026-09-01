<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('sequence');
            $table->boolean('is_break')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['establishment_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
