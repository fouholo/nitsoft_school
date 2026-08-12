<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academics\Enums\Cycle;
use App\Domain\Academics\Models\Classroom;
use App\Domain\Academics\Models\Level;
use App\Domain\Academics\Models\SchoolYear;
use App\Domain\Academics\Models\Serie;
use App\Domain\Enrollment\Models\Nationalite;
use App\Domain\Establishments\Enums\EstablishmentType;
use App\Domain\Establishments\Enums\SaasAdminType;
use App\Domain\Establishments\Models\Direction;
use App\Domain\Establishments\Models\Establishment;
use App\Domain\Establishments\Models\Foundation;
use App\Domain\Establishments\Models\Inspection;
use App\Domain\Establishments\Models\SaasAdmin;
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

        $levels = [
            ['level' => 'PS', 'level_wording' => 'Petite Section', 'cycle' => Cycle::Prescolaire->value, 'requires_series' => false],
            ['level' => 'MS', 'level_wording' => 'Moyenne Section', 'cycle' => Cycle::Prescolaire->value, 'requires_series' => false],
            ['level' => 'GS', 'level_wording' => 'Grande Section', 'cycle' => Cycle::Prescolaire->value, 'requires_series' => false],
            ['level' => 'CP1', 'level_wording' => 'CP1', 'cycle' => Cycle::Primaire->value, 'requires_series' => false],
            ['level' => 'CP2', 'level_wording' => 'CP2', 'cycle' => Cycle::Primaire->value, 'requires_series' => false],
            ['level' => 'CE1', 'level_wording' => 'CE1', 'cycle' => Cycle::Primaire->value, 'requires_series' => false],
            ['level' => 'CE2', 'level_wording' => 'CE2', 'cycle' => Cycle::Primaire->value, 'requires_series' => false],
            ['level' => 'CM1', 'level_wording' => 'CM1', 'cycle' => Cycle::Primaire->value, 'requires_series' => false],
            ['level' => 'CM2', 'level_wording' => 'CM2', 'cycle' => Cycle::Primaire->value, 'requires_series' => false],
            ['level' => '6EME', 'level_wording' => '6ème', 'cycle' => Cycle::Secondaire->value, 'requires_series' => false],
            ['level' => '5EME', 'level_wording' => '5ème', 'cycle' => Cycle::Secondaire->value, 'requires_series' => false],
            ['level' => '4EME', 'level_wording' => '4ème', 'cycle' => Cycle::Secondaire->value, 'requires_series' => false],
            ['level' => '3EME', 'level_wording' => '3ème', 'cycle' => Cycle::Secondaire->value, 'requires_series' => false],
            ['level' => '2ND', 'level_wording' => 'Seconde', 'cycle' => Cycle::Secondaire->value, 'requires_series' => true],
            ['level' => '1ERE', 'level_wording' => 'Première', 'cycle' => Cycle::Secondaire->value, 'requires_series' => true],
            ['level' => 'TLE', 'level_wording' => 'Terminale', 'cycle' => Cycle::Secondaire->value, 'requires_series' => true],
        ];

        foreach ($levels as $level) {
            Level::create($level);
        }

        $series = [
            ['serie' => 'A1', 'serie_wording' => 'Lettres-Langues'],
            ['serie' => 'A2', 'serie_wording' => 'Lettres-Philosophie'],
            ['serie' => 'C', 'serie_wording' => 'Mathématiques-Sciences physiques'],
            ['serie' => 'D', 'serie_wording' => 'Mathématiques-Sciences de la vie et de la terre'],
        ];

        foreach ($series as $serie) {
            Serie::create($serie);
        }

        $directions = [
            ['code' => 'DR-ABJ', 'direction_name' => 'Direction Régionale Abidjan', 'address' => 'Abidjan', 'phone_number' => '2722000001', 'email' => 'dr-abidjan@education.ci', 'location' => 'Abidjan'],
            ['code' => 'DR-BKE', 'direction_name' => 'Direction Régionale Bouaké', 'address' => 'Bouaké', 'phone_number' => '2731000001', 'email' => 'dr-bouake@education.ci', 'location' => 'Bouaké'],
        ];

        $createdDirections = [];

        foreach ($directions as $direction) {
            $createdDirections[$direction['code']] = Direction::create($direction);
        }

        $inspections = [
            ['codeiep' => 'IEP001', 'inspection_name' => 'Inspection Abidjan 1', 'address' => 'Abidjan', 'phone_number' => '2722000010', 'email' => 'iep-abj1@education.ci', 'location' => 'Abidjan', 'uid_direction' => $createdDirections['DR-ABJ']->uid_serveur],
            ['codeiep' => 'IEP002', 'inspection_name' => 'Inspection Abidjan 2', 'address' => 'Abidjan', 'phone_number' => '2722000011', 'email' => 'iep-abj2@education.ci', 'location' => 'Abidjan', 'uid_direction' => $createdDirections['DR-ABJ']->uid_serveur],
            ['codeiep' => 'IEP003', 'inspection_name' => 'Inspection Bouaké', 'address' => 'Bouaké', 'phone_number' => '2731000010', 'email' => 'iep-bke@education.ci', 'location' => 'Bouaké', 'uid_direction' => $createdDirections['DR-BKE']->uid_serveur],
        ];

        foreach ($inspections as $inspection) {
            Inspection::create($inspection);
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
            'type' => EstablishmentType::Secondaire,
            'address' => 'Abidjan, Côte d\'Ivoire',
            'phone' => '+225 07 00 00 00 00',
            'is_active' => true,
        ]);

        Establishment::create([
            'foundation_id' => $foundation->id,
            'name' => 'Institut Nitsoft Nord',
            'slug' => 'institut-nitsoft-nord',
            'type' => EstablishmentType::Secondaire,
            'address' => 'Bouaké, Côte d\'Ivoire',
            'phone' => '+225 07 00 00 00 01',
            'is_active' => true,
        ]);

        $mixedEstablishment = Establishment::create([
            'foundation_id' => $foundation->id,
            'name' => 'École La Pouponnière',
            'slug' => 'ecole-la-pouponniere',
            'type' => EstablishmentType::PrescolairePrimaire,
            'address' => 'Yamoussoukro, Côte d\'Ivoire',
            'phone' => '+225 07 00 00 00 02',
            'is_active' => true,
        ]);

        $mixedSchoolYear = SchoolYear::create([
            'establishment_id' => $mixedEstablishment->id,
            'label' => '2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_current' => true,
        ]);

        $grandeSection = Level::where('level', 'GS')->sole();
        $cp1 = Level::where('level', 'CP1')->sole();

        Classroom::create([
            'establishment_id' => $mixedEstablishment->id,
            'school_year_id' => $mixedSchoolYear->id,
            'level_id' => $grandeSection->id,
            'numero' => 'A',
            'capacity' => 25,
        ]);

        Classroom::create([
            'establishment_id' => $mixedEstablishment->id,
            'school_year_id' => $mixedSchoolYear->id,
            'level_id' => $cp1->id,
            'numero' => 'A',
            'capacity' => 30,
        ]);

        $superAdminUser = User::factory()->create([
            'name' => 'Super Admin SaaS',
            'email' => 'superadmin@nitsoft.test',
        ]);

        SaasAdmin::create([
            'user_id' => $superAdminUser->id,
            'type' => SaasAdminType::Main,
            'is_active' => true,
            'is_main' => true,
        ]);

        $accounts = [
            ['name' => 'Directeur Etablissement', 'email' => 'admin@nitsoft.test', 'role' => 'directeur', 'is_local_admin' => true],
            ['name' => 'Enseignant Demo', 'email' => 'teacher@nitsoft.test', 'role' => 'enseignant', 'is_local_admin' => null],
            ['name' => 'Caissier Demo', 'email' => 'accountant@nitsoft.test', 'role' => 'caissier', 'is_local_admin' => null],
            ['name' => 'Éducateur Demo', 'email' => 'educateur@nitsoft.test', 'role' => 'educateur', 'is_local_admin' => null],
        ];

        foreach ($accounts as $account) {
            $user = User::factory()->create([
                'name' => $account['name'],
                'email' => $account['email'],
            ]);

            $establishment->users()->attach($user->id, [
                'role' => $account['role'],
                'is_active' => true,
                'is_local_admin' => $account['is_local_admin'],
            ]);
        }

        $founder = User::factory()->create([
            'name' => 'Fondateur Groupe',
            'email' => 'founder@nitsoft.test',
        ]);

        $foundation->users()->attach($founder->id, [
            'role' => 'fondateur',
            'is_active' => true,
            'is_general_admin' => true,
        ]);
    }
}
