<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appreciation_scales', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('percentage')->unique();
            $table->string('appreciation');
            $table->boolean('tableau_honneur')->default(false);
            $table->boolean('tableau_excellence')->default(false);
            $table->boolean('felicitation')->default(false);
            $table->boolean('encouragement')->default(false);
            $table->string('uid_local')->nullable();
            $table->string('uid_serveur')->nullable()->unique();
            $table->string('device_id')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appreciation_scales');
    }
};
