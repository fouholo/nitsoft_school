# Composition primaire commune à toutes les classes (sans classe à la création)

*(Design validé par l'utilisateur le 2026-08-16, dans la continuité du chantier "Composition primaire toutes matières" — cf. `2026-08-15-composition-primaire-toutes-matieres-design.md`.)*

## Contexte

Depuis le chantier précédent, une composition primaire (`GradeSheet`) couvre déjà toutes les matières d'un niveau, mais reste rattachée à **une classe** (`classroom_id` obligatoire). L'utilisateur veut qu'une composition soit **commune à toutes les classes** de l'établissement — une seule composition "Composition 2" pour toute l'école primaire, pas une par classe.

**Blocage identifié et résolu avec l'utilisateur** : `GradeSheetPolicy::update()` (utilisée pour autoriser la saisie des notes) vérifie que l'utilisateur est le créateur de l'évaluation (`$user->id === $gradeSheet->teacher_id`). Avec une composition créée une fois par le directeur mais notée ensuite par différents enseignants selon leurs classes, cette vérification devient incorrecte. Décision : la création d'une composition est réservée au rôle **directeur** ; la saisie des notes (écrans `Students`/`EnterStudent`) abandonne l'autorisation basée sur `GradeSheetPolicy::update()` au profit d'une autorisation directe par affectation à la classe de l'élève noté — indépendante de qui a créé la composition.

Cette spec ne touche pas le secondaire (`GradeSheetPolicy`, `GradeSheets\Secondaire\Index`, `Enter.php` restent inchangés — `teacher_id`/assignation par classe+matière continue d'y avoir un sens, une évaluation secondaire reste créée et notée par le même enseignant).

## Partie 1 — Schéma

### Migration `make_classroom_id_nullable_on_grade_sheets_table`

```php
Schema::table('grade_sheets', function (Blueprint $table): void {
    $table->foreignId('classroom_id')->nullable()->change();
});
```

Le secondaire continue de toujours renseigner `classroom_id` (validation applicative inchangée dans `GradeSheets\Secondaire\Index`) — seul le primaire l'omet désormais.

## Partie 2 — `GradeSheets\Primaire\Index` : création réservée au directeur, sans classe

- Formulaire réduit à `composition_number`, `title`, `graded_on` (déjà le cas pour `composition_number`/`title`/`graded_on` — retire seulement `classroom_id`).
- `save()` : plus de vérification `hasBroadGradeAccess()`/`isAssignedToClassroom()`, plus d'appel à `$this->authorize('create', GradeSheet::class)` (la policy partagée avec le secondaire ne couvre pas ce cas). Remplacé par :
  ```php
  abort_unless(Auth::user()->currentRole() === 'directeur', 403);
  ```
  `GradeSheet::create([...$data, 'classroom_id' => null, 'type' => 'composition', 'teacher_id' => $user->id])`.
- `render()` : plus besoin de `classrooms`/`assignments` (n'existaient que pour peupler le sélecteur de classe et le filtrage par affectation, disparus). La liste des compositions perd sa colonne "Classe".
- Le bouton "Nouvelle composition" n'est visible que pour un directeur — condition Blade `@if (auth()->user()->currentRole() === 'directeur')` (pas de policy dédiée pour un cas aussi ponctuel, cohérent avec l'inline check de `save()`).

## Partie 3 — `GradeSheets\Primaire\Students` : liste de tous les élèves primaire autorisés

- `mount()` : `$this->authorize('viewAny', GradeSheet::class)` (établissement + pas caissier, inchangé) — plus d'`authorize('update', $gradeSheet)` ni de vérification de cycle sur `$gradeSheet->classroom` (qui n'existe plus).
- `render()` : liste tous les élèves inscrits activement dans une classe primaire (cycle `Primaire`, `isGradable()`), avec leur classe affichée en colonne. Filtrage par affectation pour un enseignant simple, accès large pour directeur/educateur — même logique que `hasBroadGradeAccess()`/`TeacherAssignment` déjà utilisée dans `GradeSheets\Primaire\Index` avant ce chantier :
  ```php
  private function hasBroadGradeAccess(User $user): bool
  {
      return $user->hasAdminRightsOnCurrentEstablishment()
          || RolePermissions::can($user->currentRole(), 'grades.enter');
  }

  public function render()
  {
      $user = Auth::user();
      $isAdmin = $this->hasBroadGradeAccess($user);

      $assignedClassroomIds = $isAdmin
          ? null
          : TeacherAssignment::where('user_id', $user->id)
              ->whereHas('classroom.level', fn ($q) => $q->where('cycle', Cycle::Primaire))
              ->pluck('classroom_id');

      $students = Student::query()
          ->with(['enrollments' => fn ($q) => $q->where('status', 'active')->with('classroom.level')])
          ->whereHas('enrollments', function ($q) use ($assignedClassroomIds): void {
              $q->where('status', 'active')
                  ->whereHas('classroom.level', fn ($q2) => $q2->where('cycle', Cycle::Primaire));

              if ($assignedClassroomIds !== null) {
                  $q->whereIn('classroom_id', $assignedClassroomIds);
              }
          })
          ->orderBy('last_name')
          ->get();

      return view('livewire.grading.grade-sheets.primaire.students', [
          'students' => $students,
      ]);
  }
  ```
  Vue : colonne "Classe" ajoutée (résolue via `$student->enrollments->first()->classroom->name`, déjà chargée).

## Partie 4 — `GradeSheets\Primaire\EnterStudent` : classe résolue via l'inscription de l'élève

- `mount()` : résout la classe de l'élève via son inscription active plutôt que via `$gradeSheet->classroom` :
  ```php
  $classroom = $student->enrollments()->where('status', 'active')->first()?->classroom;
  abort_unless($classroom && $classroom->level->cycle === Cycle::Primaire, 404);

  $user = Auth::user();
  abort_unless($this->hasBroadGradeAccess($user) || $user->isAssignedToClassroom($classroom->id), 403);

  $this->classroom = $classroom; // nouvelle propriété publique, remplace les usages de $gradeSheet->classroom
  ```
- Tous les usages internes de `$this->gradeSheet->classroom->...` (dans `subjects()`, `preview()`, `save()` pour `school_year_id`) deviennent `$this->classroom->...`.
- `save()` : `ReportCard::updateOrCreate([..., 'school_year_id' => $this->classroom->school_year_id, ...], ['classroom_id' => $this->classroom->id, ...])` (inchangé dans l'esprit, juste la source de la classe qui change).

## Partie 5 — `ReportCardService` : scoping par inscription de l'élève

`generate()` (méthode privée partagée) : le filtrage "notes de cette classe, cette composition" ne peut plus passer par `whereHas('gradeSheet', ...->where('classroom_id', ...))` côté primaire (n'existe plus sur `GradeSheet`). Remplacé, pour la branche `PrimaryGrade`, par un filtre sur l'inscription active de l'élève :

```php
$gradeRows = $classroom->level->cycle === Cycle::Primaire
    ? PrimaryGrade::query()
        ->with('primarySubject')
        ->whereNotNull('score')
        ->whereHas('gradeSheet', $scopeGradeSheets) // filtre uniquement composition_number désormais
        ->whereHas('student.enrollments', fn ($q) => $q->where('classroom_id', $classroom->id)->where('status', 'active'))
        ->get()->all()
    : Grade::query()->with('gradeSheet.subject')->whereNotNull('score')->whereHas('gradeSheet', $scopeToClassroom)->get()->all();
```

(Le secondaire garde son scoping actuel par `gradeSheet.classroom_id`, inchangé.)

`maxScoreFor()`/`weightedAverage()` ne peuvent plus lire `$grade->gradeSheet->classroom->level` (n'existe plus) — le niveau est déjà connu du contexte appelant (`$classroom` dans `generate()`/`subjectBreakdown()`, passé en paramètre). Signature élargie :

```php
private function weightedAverage(Collection $grades, Classroom $classroom): ?float
private function maxScoreFor(Grade|PrimaryGrade $grade, Classroom $classroom): float
{
    if ($grade instanceof PrimaryGrade) {
        return $grade->primarySubject->bareme($classroom->level) ?? 20.0;
    }

    return (float) $grade->gradeSheet->max_score;
}
```
`generalAverage()`/`subjectBreakdown()` propagent `$classroom` à `weightedAverage()`.

## Tests à écrire/adapter

- `GradeSheets\PrimaireTest.php` : création sans classe, réservée au directeur (educateur/enseignant désormais refusés même avec affectation).
- `GradeSheets\PrimaireStudentsTest.php` : la liste couvre plusieurs classes ; un enseignant ne voit que les élèves de ses classes affectées ; un educateur/directeur voit tout.
- `GradeSheets\PrimaireEnterStudentTest.php` : classe résolue via l'inscription active (pas via l'évaluation) ; un enseignant assigné à la classe de l'élève peut noter même s'il n'a pas créé la composition ; un enseignant non affecté est refusé.
- `ReportCardServiceTest.php`/`ReportCards\PrimaireTest.php` : fixtures avec une composition sans `classroom_id`, plusieurs classes/élèves sous la même composition, génération par classe qui n'agrège que les élèves de cette classe.

## Vérification

1. `php artisan migrate`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur les données WAMP existantes : créer une composition en tant que directeur (aucune classe demandée), vérifier qu'un educateur peut noter des élèves de n'importe laquelle des classes primaire de l'établissement sous cette même composition, puis générer le bulletin d'une classe et vérifier qu'il n'agrège que ses propres élèves.
