<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Establishments\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Jeu de données de démonstration pour le développement local. Mot de
     * passe commun à tous les comptes : "password".
     */
    public function run(): void
    {
        $establishment = Establishment::create([
            'name' => 'Groupe Scolaire Excellence',
            'slug' => 'groupe-scolaire-excellence',
            'type' => 'college',
            'address' => 'Abidjan, Côte d\'Ivoire',
            'phone' => '+225 07 00 00 00 00',
            'timezone' => 'Africa/Abidjan',
            'is_active' => true,
        ]);

        $accounts = [
            ['name' => 'Fondateur SaaS', 'email' => 'superadmin@nitsoft.test', 'role' => 'super_admin'],
            ['name' => 'Directeur Etablissement', 'email' => 'admin@nitsoft.test', 'role' => 'admin'],
            ['name' => 'Enseignant Demo', 'email' => 'teacher@nitsoft.test', 'role' => 'teacher'],
            ['name' => 'Comptable Demo', 'email' => 'accountant@nitsoft.test', 'role' => 'accountant'],
        ];

        foreach ($accounts as $account) {
            $user = User::factory()->create([
                'name' => $account['name'],
                'email' => $account['email'],
            ]);

            $establishment->users()->attach($user->id, [
                'role' => $account['role'],
                'is_active' => true,
            ]);
        }
    }
}
