<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('establishments')->whereIn('type', ['college', 'lycee'])->update(['type' => 'secondaire']);
        DB::table('establishments')->where('type', 'préscolaire-primaire')->update(['type' => 'prescolaire_primaire']);
    }

    public function down(): void
    {
        DB::table('establishments')->where('type', 'prescolaire_primaire')->update(['type' => 'préscolaire-primaire']);
        DB::table('establishments')->where('type', 'secondaire')->update(['type' => 'lycee']);
    }
};
