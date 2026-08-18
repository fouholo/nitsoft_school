<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La table `roles` avait divergé des rôles réellement utilisés dans
 * establishment_user.role : "comptable" n'était référencé nulle part dans le
 * code, tandis que "caissier", "educateur" et "parent" (réellement utilisés)
 * en étaient absents.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->where('code', 'comptable')->delete();

        DB::table('roles')->insertOrIgnore([
            ['code' => 'caissier', 'wording' => 'Caissier', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'educateur', 'wording' => 'Éducateur', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'parent', 'wording' => 'Parent', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('code', ['caissier', 'educateur', 'parent'])->delete();

        DB::table('roles')->insertOrIgnore([
            ['code' => 'comptable', 'wording' => 'Comptable', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
