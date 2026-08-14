<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('abbreviation', 10)->nullable()->after('name');
        });

        DB::table('subjects')->orderBy('id')->each(function (object $subject): void {
            DB::table('subjects')->where('id', $subject->id)->update([
                'abbreviation' => mb_strtoupper(mb_substr((string) $subject->name, 0, 10)),
            ]);
        });

        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('abbreviation', 10)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('abbreviation');
        });
    }
};
