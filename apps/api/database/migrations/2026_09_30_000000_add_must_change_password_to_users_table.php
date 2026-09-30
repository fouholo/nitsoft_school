<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Tout compte créé par un tiers reçoit le mot de passe par défaut
 * (User::DEFAULT_PASSWORD) : ce drapeau oblige son titulaire à en choisir un
 * autre à la première connexion (middleware EnsurePasswordIsChanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        // Les comptes existants qui utilisent encore le mot de passe par
        // défaut sont marqués eux aussi. La vérification d'un hachage est
        // volontairement lente (~0,2 s par compte) : la migration peut durer
        // quelques minutes sur une base de plusieurs centaines de comptes.
        DB::table('users')->select(['id', 'password'])->orderBy('id')->chunkById(200, function ($users): void {
            $ids = [];

            foreach ($users as $user) {
                if (Hash::check(User::DEFAULT_PASSWORD, (string) $user->password)) {
                    $ids[] = $user->id;
                }
            }

            if ($ids !== []) {
                DB::table('users')->whereIn('id', $ids)->update(['must_change_password' => true]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
