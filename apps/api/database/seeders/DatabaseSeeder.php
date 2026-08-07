<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Enrollment\Models\Nationalite;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
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
        $nationalites = [
            ['code' => 'CIV', 'libelle' => 'Ivoirienne'],
            ['code' => 'FRA', 'libelle' => 'Française'],
            ['code' => 'MLI', 'libelle' => 'Malienne'],
            ['code' => 'BFA', 'libelle' => 'Burkinabè'],
            ['code' => 'GHA', 'libelle' => 'Ghanéenne'],
        ];

        foreach ($nationalites as $nationalite) {
            Nationalite::create($nationalite);
        }

        $foundation = Foundation::create([
            'name' => 'Groupe Nitsoft Education',
            'slug' => 'groupe-nitsoft-education',
            'is_active' => true,
        ]);

        $establishment = Establishment::create([
            'foundation_id' => $foundation->id,
            'name' => 'Groupe Scolaire Excellence',
            'slug' => 'groupe-scolaire-excellence',
            'type' => 'college',
            'address' => 'Abidjan, Côte d\'Ivoire',
            'phone' => '+225 07 00 00 00 00',
            'timezone' => 'Africa/Abidjan',
            'is_active' => true,
        ]);

        Establishment::create([
            'foundation_id' => $foundation->id,
            'name' => 'Institut Nitsoft Nord',
            'slug' => 'institut-nitsoft-nord',
            'type' => 'lycee',
            'address' => 'Bouaké, Côte d\'Ivoire',
            'phone' => '+225 07 00 00 00 01',
            'timezone' => 'Africa/Abidjan',
            'is_active' => true,
        ]);

        $mixedEstablishment = Establishment::create([
            'foundation_id' => $foundation->id,
            'name' => 'École La Pouponnière',
            'slug' => 'ecole-la-pouponniere',
            'type' => 'préscolaire-primaire',
            'address' => 'Yamoussoukro, Côte d\'Ivoire',
            'phone' => '+225 07 00 00 00 02',
            'timezone' => 'Africa/Abidjan',
            'is_active' => true,
        ]);

        $mixedSchoolYear = SchoolYear::create([
            'establishment_id' => $mixedEstablishment->id,
            'label' => '2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_current' => true,
        ]);

        Classroom::create([
            'establishment_id' => $mixedEstablishment->id,
            'school_year_id' => $mixedSchoolYear->id,
            'name' => 'Grande Section A',
            'level' => 'Grande Section',
            'cycle' => Cycle::Prescolaire,
            'capacity' => 25,
        ]);

        Classroom::create([
            'establishment_id' => $mixedEstablishment->id,
            'school_year_id' => $mixedSchoolYear->id,
            'name' => 'CP A',
            'level' => 'CP',
            'cycle' => Cycle::Primaire,
            'capacity' => 30,
        ]);

        $accounts = [
            ['name' => 'Super Admin SaaS', 'email' => 'superadmin@nitsoft.test', 'role' => 'super_admin'],
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

        $founder = User::factory()->create([
            'name' => 'Fondateur Groupe',
            'email' => 'founder@nitsoft.test',
        ]);

        $foundation->users()->attach($founder->id, [
            'role' => 'founder',
            'is_active' => true,
        ]);
    }
}
