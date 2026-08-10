<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->insert(['code' => 'caissier', 'wording' => 'Caissier', 'created_at' => $now, 'updated_at' => $now]);

            DB::table('establishment_user')->where('role', 'comptable')->update(['role' => 'caissier']);
            DB::table('foundation_user')->where('role', 'comptable')->update(['role' => 'caissier']);

            DB::table('roles')->where('code', 'comptable')->delete();

            DB::table('roles')->insert(['code' => 'educateur', 'wording' => 'Éducateur', 'created_at' => $now, 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->insert(['code' => 'comptable', 'wording' => 'Comptable', 'created_at' => $now, 'updated_at' => $now]);

            DB::table('establishment_user')->where('role', 'caissier')->update(['role' => 'comptable']);
            DB::table('foundation_user')->where('role', 'caissier')->update(['role' => 'comptable']);

            DB::table('roles')->where('code', 'caissier')->delete();
            DB::table('roles')->where('code', 'educateur')->delete();
        });
    }
};
