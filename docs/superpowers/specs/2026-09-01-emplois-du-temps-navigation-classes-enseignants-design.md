# Emplois du temps — navigation par Classes / Enseignants

*(Validé par l'utilisateur le 2026-09-01. Sous-chantier du module « emplois du temps du secondaire », voir `docs/superpowers/specs/2026-09-01-emplois-du-temps-secondaire-design.md`.)*

## Contexte

Le module livré (commits `54b34f9`, `3b9c04d`) expose la grille par classe (`academics.timetable.{classroom}`, éditable) uniquement via un lien noyé dans chaque ligne de `academics.classrooms.index`, et une vue personnelle (`academics.timetable.mine`) limitée à l'utilisateur connecté. Le personnel administratif (fondateur, directeur, gestionnaire, éducateur, caissier) n'a aujourd'hui aucun moyen de consulter l'emploi du temps d'un enseignant précis autre que lui-même.

Demande : pour ces rôles, organiser la consultation des emplois du temps en deux groupes clairement séparés — **Classes** et **Enseignants**.

## Périmètre

- Concerne uniquement le personnel administratif (fondateur/directeur/gestionnaire/éducateur/caissier). Un enseignant garde exactement son point d'entrée actuel (« Mon emploi du temps ») — les deux coexistent, aucun changement de comportement pour ce rôle.
- Onglet Classes : classes secondaires de l'**année scolaire courante** uniquement (pas d'historique).
- Onglet Enseignants : **tous** les enseignants actifs de l'établissement (pas seulement ceux ayant déjà une séance) — une grille vide est un signal utile (« rien de planifié pour lui »).
- La grille par enseignant est **en lecture seule** : toute création/modification de séance continue de se faire depuis la grille de la classe concernée (où vit déjà la logique de créneau/conflit).

## 1. Autorisation

Nouvelle méthode dans `App\Policies\TimetableSessionPolicy` :

```php
/**
 * Écran de navigation "Emplois du temps" (Classes/Enseignants) et la
 * grille en lecture seule d'un enseignant tiers — réservés au personnel
 * administratif, explicitement hors périmètre pour un enseignant (qui
 * garde MySchedule comme unique point d'entrée).
 */
public function browseStaff(User $user): bool
{
    return $this->isMemberOfCurrentEstablishment($user)
        && $user->currentRole() !== 'enseignant';
}
```

## 2. Écran `App\Livewire\Academics\Timetable\Browse`

Route `academics.timetable.browse` → `/academics/timetable` (déclarée avant `/academics/timetable/{classroom}` dans `routes/academics.php`, comme `/slots` et `/mine` aujourd'hui).

```php
class Browse extends Component
{
    public string $tab = 'classes'; // 'classes' | 'teachers'

    public function mount(): void
    {
        $this->authorize('browseStaff', TimetableSession::class);
    }

    public function render()
    {
        $establishmentId = (int) app('currentEstablishmentId');
        $currentSchoolYearId = SchoolYear::where('is_current', true)->value('id');

        return view('livewire.academics.timetable.browse', [
            'classrooms' => Classroom::whereHas('level', fn ($q) => $q->where('cycle', Cycle::Secondaire))
                ->where('school_year_id', $currentSchoolYearId)
                ->orderBy('name')
                ->get(),
            'teachers' => Establishment::find($establishmentId)
                ->users()
                ->wherePivot('role', 'enseignant')
                ->wherePivot('is_active', true)
                ->orderBy('name')
                ->get(),
        ])->title(__('Emplois du temps'));
    }
}
```

Vue : deux boutons d'onglet pilotés par Alpine (`x-data="{ tab: @entangle('tab') }"` — ou plus simple, deux propriétés Livewire déjà présentes côté serveur, bascule par `wire:click="$set('tab', 'teachers')"` sans rechargement de collection puisque les deux listes sont déjà chargées). Chaque ligne de classe pointe vers `academics.timetable.index` (inchangé), chaque ligne d'enseignant vers `academics.timetable.teachers.show`.

## 3. Écran `App\Livewire\Academics\Timetable\TeacherSchedule`

Route `academics.timetable.teachers.show` → `/academics/timetable/teachers/{user}` (paramètre nommé `user` pour bénéficier du binding implicite sur le modèle `User`).

```php
class TeacherSchedule extends Component
{
    public User $teacher;

    public function mount(User $user): void
    {
        $this->authorize('browseStaff', TimetableSession::class);

        $isActiveTeacherHere = $user->establishments()
            ->wherePivot('role', 'enseignant')
            ->wherePivot('is_active', true)
            ->where('establishments.id', (int) app('currentEstablishmentId'))
            ->exists();

        abort_unless($isActiveTeacherHere, 404);

        $this->teacher = $user;
    }

    public function render()
    {
        $sessions = TimetableSession::where('user_id', $this->teacher->id)
            ->with(['subject', 'classroom'])
            ->get()
            ->keyBy(fn (TimetableSession $s) => $s->day_of_week->value.'-'.$s->timetable_slot_id);

        return view('livewire.academics.timetable.teacher-schedule', [
            'days' => DayOfWeek::cases(),
            'timetableSlots' => TimetableSlot::orderBy('sequence')->get(),
            'sessions' => $sessions,
        ])->title(__(':teacher — Emploi du temps', ['teacher' => $this->teacher->name]));
    }
}
```

La vue `teacher-schedule.blade.php` reprend telle quelle la grille de `my-schedule.blade.php` (même structure de tableau, mêmes variables `days`/`timetableSlots`/`sessions`) — pas d'extraction en partial commun pour ce sous-chantier (deux fichiers quasi identiques mais chacun avec son propre en-tête contextuel : « Mon emploi du temps » sans sélecteur, « Emploi du temps — *Nom* » avec un fil d'Ariane retour vers l'onglet Enseignants). Une factorisation future reste possible mais n'est pas nécessaire pour livrer ce chantier proprement — deux fichiers courts valent mieux qu'une abstraction prématurée pour deux usages.

## 4. Retrait du lien par ligne dans `academics.classrooms.index`

Le lien « Emploi du temps » ajouté sur chaque ligne secondaire (chantier précédent) est retiré : le nouvel écran `Browse` (onglet Classes) devient le point d'entrée unique, évite deux chemins de navigation vers la même grille.

## 5. Navigation

`resources/views/layouts/app.blade.php` : nouvelle entrée top-level « Emplois du temps » (icône `calendar-check`), juste avant « Mon emploi du temps », gatée par `['ability' => 'browseStaff', 'model' => \App\Domain\Timetable\Models\TimetableSession::class]`. « Mon emploi du temps » et « Grille de créneaux » restent inchangés.

## Hors périmètre

- Édition de séance depuis la grille par enseignant (reste sur la grille par classe).
- Filtre par année scolaire dans l'onglet Enseignants (un enseignant n'a qu'un seul "présent", pas de notion d'historique ici — ses séances sont déjà implicitement de l'année courante via les classes).
- Extraction d'un partial Blade commun entre `MySchedule` et `TeacherSchedule` (deux vues distinctes, doublon accepté pour ce chantier).

## Tests

`tests/Feature/Policies/TimetablePolicyTest.php` (extension) : `browseStaff` refuse un enseignant, autorise fondateur/directeur/gestionnaire/éducateur/caissier ; cloisonnement multi-établissement.

`tests/Feature/Livewire/Academics/Timetable/BrowseTest.php` (nouveau) :
- Un enseignant qui accède à la route reçoit 403.
- L'onglet Classes ne contient que les classes secondaires de l'année courante (pas les autres années, pas le préscolaire/primaire).
- L'onglet Enseignants contient tous les enseignants actifs, y compris un enseignant sans aucune séance planifiée.

`tests/Feature/Livewire/Academics/Timetable/TeacherScheduleTest.php` (nouveau) :
- Affiche les séances du bon enseignant, pas celles d'un collègue.
- 404 si l'utilisateur ciblé n'est pas un enseignant actif de l'établissement courant.
- 403 si l'utilisateur connecté est lui-même enseignant (même en visant son propre id — il doit passer par `MySchedule`, pas par cette route).
- Cloisonnement multi-établissement.

`tests/Feature/Livewire/Academics/Classrooms/IndexTest.php` : mise à jour de l'assertion existante sur le lien « Emploi du temps » (retiré).

## Vérification

1. Bascule sur base SQLite jetable, `migrate:fresh --seed`, suite Pest complète, PHPStan, Pint scopé.
2. Vérification manuelle Playwright : connexion en directeur, onglets Classes/Enseignants, clic sur un enseignant sans séance (grille vide), clic sur un enseignant avec séances, tentative d'accès direct par URL en tant qu'enseignant (403).
3. Migration/données réelles : aucun changement de schéma pour ce chantier — pas de migration à appliquer en base réelle.
4. Commit puis mise à jour de la mémoire projet si un piège notable est rencontré.
