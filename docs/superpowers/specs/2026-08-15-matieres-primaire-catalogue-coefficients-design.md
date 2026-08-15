# Catalogue de matières du primaire avec coefficients par niveau

*(Design validé par l'utilisateur le 2026-08-15.)*

## Contexte

Le préscolaire/primaire et le secondaire utilisaient jusqu'ici le même catalogue de matières (`Subject`, global SaaS) et le même mécanisme de coefficient (`SubjectCoefficient`, une ligne par établissement/niveau/série/matière). Nouvelle demande : le primaire doit avoir son propre catalogue, indépendant de `Subject`, où chaque matière porte directement ses 6 coefficients (un par niveau CP1→CM2) comme colonnes, plutôt que comme lignes séparées. Et surtout : à la saisie d'une évaluation primaire, seules les matières ayant un coefficient renseigné (non nul) **pour le niveau précis de la classe** doivent être proposées — c'est un changement de granularité (aujourd'hui le filtre est par cycle, demain il sera par niveau).

Ce chantier a été discuté en même temps qu'un chantier plus petit resté en cours : scinder l'écran "Coefficients par matière" par cycle. Décision : cette nouvelle table **remplace** entièrement ce qu'aurait été la branche primaire de cet écran — inutile de la construire. "Coefficients par matière" redevient donc un écran simple, réservé au secondaire.

Trois décisions structurantes validées avec l'utilisateur :
1. `PrimarySubject` est **totalement indépendant** de `Subject` (pas de `subject_id` de rattachement) — impacte directement `GradeSheet` et `ReportCardService`, qui référencent aujourd'hui `Subject` pour les deux cycles.
2. `PrimarySubject` est un **catalogue global SaaS** (comme `Subject`/`Level`/`Domain`), pas configurable par établissement.
3. Nouveau lien de nav dédié, dans le bloc SaaS admin (comme "Matières"), séparé de "Coefficients par matière" (qui reste secondaire uniquement).

## Partie 0 — `SubjectCoefficients` redevient un écran simple, secondaire uniquement

Retour sur ce qui était en cours de conception avant ce nouvel ajout : plus de split ni de dispatcher.

- `SubjectCoefficientPolicy::viewAny()`/`create()` : ajouter la même garde que `TermPolicy` — refusé si l'établissement courant n'est pas `EstablishmentType::Secondaire`. Fait disparaître automatiquement le lien de nav "Coefficients par matière" pour un établissement préscolaire/primaire (même mécanisme que "Périodes").
- `SubjectCoefficients\Index::render()` : `'levels' => Level::where('cycle', Cycle::Secondaire)->orderBy('level_wording')->get()` (au lieu de tous les niveaux).
- `subjectsForSelectedLevel()` : simplifié, toujours `Subject::where('is_secondaire', true)` (la branche `Cycle::Primaire` devient morte, à supprimer).
- Tests : `SubjectCoefficientsTest.php` — pinner l'établissement à `EstablishmentType::Secondaire`, ajouter un test "un établissement préscolaire/primaire n'a pas accès à l'écran" (`assertForbidden`).

## Partie 1 — Modèle `PrimarySubject`

### Migration `create_primary_subjects_table`

```php
Schema::create('primary_subjects', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->string('abbreviation', 10);
    $table->decimal('coefficient_cp1', 4, 2)->nullable();
    $table->decimal('coefficient_cp2', 4, 2)->nullable();
    $table->decimal('coefficient_ce1', 4, 2)->nullable();
    $table->decimal('coefficient_ce2', 4, 2)->nullable();
    $table->decimal('coefficient_cm1', 4, 2)->nullable();
    $table->decimal('coefficient_cm2', 4, 2)->nullable();

    $table->char('uid', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();

    $table->timestamps();
    $table->softDeletes();
});
```

### `App\Domain\Academics\Models\PrimarySubject`

`HasFactory`, `SoftDeletes`, `Syncable` (`uidPrefix()` → `'224'`, libre — vérifié contre tous les préfixes existants). `$fillable` : `name`, `abbreviation`, les 6 colonnes de coefficient, + colonnes de synchro. `$casts` : les 6 colonnes en `decimal:2`.

Méthode centrale, seule source de vérité pour le mapping niveau → colonne (réutilisée par l'écran de saisie et par `ReportCardService`) :
```php
public static function coefficientColumn(Level $level): string
{
    return match ($level->level) {
        'CP1' => 'coefficient_cp1',
        'CP2' => 'coefficient_cp2',
        'CE1' => 'coefficient_ce1',
        'CE2' => 'coefficient_ce2',
        'CM1' => 'coefficient_cm1',
        'CM2' => 'coefficient_cm2',
        default => throw new \InvalidArgumentException("Niveau primaire inconnu : {$level->level}"),
    };
}

public function coefficientFor(Level $level): ?float
{
    $value = $this->{self::coefficientColumn($level)};

    return $value !== null ? (float) $value : null;
}
```

### `App\Policies\PrimarySubjectPolicy`

Copie conforme de `SubjectPolicy` : toutes les méthodes retournent `false`, accès uniquement via le bypass `Gate::before` du Super Admin SaaS (même docblock explicatif).

### Écran `App\Livewire\Academics\PrimarySubjects\Index`

Calqué sur `Academics\Subjects\Index` (grille CRUD SaaS admin) mais avec 6 champs de coefficient au lieu des cases à cocher de cycle/domaine :
- `showForm`, `editingId`, `name`, `abbreviation`, `coefficient_cp1`…`coefficient_cm2` (propriétés `?string` pour coller au pattern `wire:model` + validation `nullable|numeric`, comme `SubjectCoefficients\Index::$coefficients` aujourd'hui).
- `save()` : valide `name` (required), `abbreviation` (required, max 10), chaque coefficient (`nullable`, `numeric`, `min:0.5`, `max:20`).
- Vue : tableau avec une ligne par matière, colonnes Nom / Abréviation / CP1 / CP2 / CE1 / CE2 / CM1 / CM2, formulaire d'ajout/édition avec les 8 champs.
- Nav (`layouts/app.blade.php`, bloc SaaS admin) : nouvelle entrée `['label' => 'Matières du primaire', 'route' => 'academics.primary-subjects.index', ...]`, juste après "Matières".
- Route : `routes/academics.php`, `Route::get('/primary-subjects', PrimarySubjectsIndex::class)->name('primary-subjects.index');`.

## Partie 2 — Saisie des évaluations primaire (`GradeSheets\Primaire\Index`)

### Migration `make_subject_id_nullable_and_add_primary_subject_id_to_grade_sheets_table`

```php
Schema::table('grade_sheets', function (Blueprint $table): void {
    $table->foreignId('subject_id')->nullable()->change();
    $table->foreignId('primary_subject_id')->nullable()->after('subject_id')->constrained()->cascadeOnDelete();
});
```

### `App\Domain\Grading\Models\GradeSheet`

- `$fillable` += `primary_subject_id`.
- Nouvelle relation `primarySubject(): BelongsTo` → `belongsTo(PrimarySubject::class)`.
- Docblock de classe complété : `@property-read Subject|null $subject Nul pour une évaluation préscolaire/primaire (primary_subject_id renseigné à la place).`

### `App\Livewire\Grading\GradeSheets\Primaire\Index`

- `$subject_id` → `$primary_subject_id` (propriété renommée).
- `updatedClassroomId()` : reset `primary_subject_id`, `composition_number` (inchangé sinon).
- `subjectsForSelectedLevel()` (nouvelle méthode privée, remplace le filtre `is_prescolaire_primaire` fixe) :
  ```php
  private function subjectsForClassroom(Classroom $classroom): Collection
  {
      $column = PrimarySubject::coefficientColumn($classroom->level);

      return PrimarySubject::whereNotNull($column)->orderBy('name')->get();
  }
  ```
  Utilisée à la fois dans `render()` (liste proposée) et dans le calcul des matières "classe entière" pour un enseignant (remplace `Subject::where('is_prescolaire_primaire', true)->get()` dans la logique d'affectation classe entière).
- `save()` : valide `primary_subject_id` (required, exists:primary_subjects,id) au lieu de `subject_id`. Remplace le contrôle `$subject->is_prescolaire_primaire` par :
  ```php
  $primarySubject = PrimarySubject::findOrFail($data['primary_subject_id']);

  if ($primarySubject->coefficientFor($classroom->level) === null) {
      throw ValidationException::withMessages([
          'primary_subject_id' => "Cette matière n'est pas configurée pour ce niveau.",
      ]);
  }
  ```
  `GradeSheet::create([...$data, 'type' => 'composition', 'teacher_id' => $user->id])` — `$data` contient désormais `primary_subject_id` et non `subject_id` (qui reste `null`).

### Vue `livewire/grading/grade-sheets/primaire/index.blade.php`

- `wire:model="subject_id"` → `wire:model="primary_subject_id"`, options depuis `$subjects` (déjà filtré par niveau).
- Ligne du tableau : `{{ $gradeSheet->primarySubject?->name }}` au lieu de `{{ $gradeSheet->subject?->name }}`.

## Partie 3 — Bulletins (`ReportCardService`)

- `coefficientsFor(Classroom $classroom): Collection<int, float>` — **changement de signature** (avant : `Collection<int, SubjectCoefficient>` keyé par `subject_id`, avec `->coefficient` ; après : `Collection<int, float>` keyé directement par identifiant de matière, valeur = coefficient). Ce changement s'applique aux deux cycles pour garder une interface uniforme :
  ```php
  private function coefficientsFor(Classroom $classroom): Collection
  {
      return $classroom->level->cycle === Cycle::Primaire
          ? $this->primaryCoefficientsFor($classroom)
          : $this->secondaryCoefficientsFor($classroom);
  }

  private function primaryCoefficientsFor(Classroom $classroom): Collection
  {
      $column = PrimarySubject::coefficientColumn($classroom->level);

      return PrimarySubject::query()
          ->whereNotNull($column)
          ->get()
          ->mapWithKeys(fn (PrimarySubject $s) => [$s->id => (float) $s->{$column}]);
  }

  private function secondaryCoefficientsFor(Classroom $classroom): Collection
  {
      return SubjectCoefficient::query()
          ->where('level_id', $classroom->level_id)
          ->where('serie_id', $classroom->serie_id)
          ->get()
          ->mapWithKeys(fn (SubjectCoefficient $c) => [$c->subject_id => (float) $c->coefficient]);
  }
  ```
- `generalAverage()` : `$coefficient = (float) $coefficients->get($subjectId);` (au lieu de `->coefficient`).
- `subjectBreakdown()` : `$coefficients->get($subjectId)` est déjà un `?float`, plus besoin du `?->coefficient`.
- Le "subjectId" utilisé partout dans `ReportCardService` (groupBy, coefficients, breakdown) reste `gradeSheet->subject_id` **ou** `gradeSheet->primary_subject_id` selon le cycle — remplacé par une clé unifiée `$grade->gradeSheet->subject_id ?? $grade->gradeSheet->primary_subject_id` partout où le code groupe/regarde par matière.
- `subjectFor()` : retourne `Subject|PrimarySubject` — `$grades->first()->gradeSheet->subject ?? $grades->first()->gradeSheet->primarySubject`.
- Eager loading : `->with('gradeSheet.subject')` devient `->with(['gradeSheet.subject', 'gradeSheet.primarySubject'])` (`generate()` et `subjectBreakdown()`).
- `assertCoefficientsConfigured()` : le message d'erreur listant les matières manquantes doit gérer les deux cas (`Subject::whereIn(...)` ou `PrimarySubject::whereIn(...)` selon le cycle de la classe).

### `App\Domain\Grading\ValueObjects\SubjectAverage`

`public readonly Subject $subject` → `public readonly Subject|PrimarySubject $subject` (les deux exposent `name`/`abbreviation`, seuls champs utilisés en aval — PDF, écran).

### PDF (`resources/views/pdf/report-card.blade.php`)

Aucun changement de template nécessaire : `{{ $row->subject->name }}` fonctionne pour les deux types grâce au duck typing de Blade (pas de vérification statique à ce niveau).

## Partie 4 — Nettoyage / hors périmètre

- Les 6 lignes `SubjectCoefficient` existantes pour des niveaux primaire dans la base WAMP deviennent orphelines (plus jamais lues par `ReportCardService` après ce chantier). Aucune donnée réelle de note primaire n'existe encore (vérifié : 0 `GradeSheet` avec `composition_number` renseigné) — pas de backfill nécessaire, ces lignes restent simplement inertes.
- Pas de changement à `Subject`/`SubjectCoefficient` eux-mêmes : ils continuent de servir le secondaire tels quels.
- Pas de gestion par établissement pour `PrimarySubject` (catalogue global, décision validée).

## Tests à écrire/adapter

- `SubjectCoefficientsTest.php` : établissement pinné secondaire + test d'accès refusé pour un établissement primaire (Partie 0).
- Nouveau `tests/Feature/Livewire/Academics/PrimarySubjectsTest.php` : CRUD réservé au Super Admin SaaS (refus pour tout le monde d'autre, y compris directeur), calqué sur `SubjectsTest.php`.
- `tests/Feature/Livewire/Grading/GradeSheets/PrimaireTest.php` : adapter tous les tests existants (`subject_id` → `primary_subject_id`, `Subject::factory()` → `PrimarySubject::factory()` avec coefficient renseigné pour le niveau de la classe testée). Nouveau test : une matière dont le coefficient est nul pour le niveau de la classe est absente de la liste et rejetée côté serveur si soumise.
- `tests/Feature/Domain/ReportCardServiceTest.php` : adapter le test de composition primaire pour utiliser `PrimarySubject` (coefficient sur la bonne colonne de niveau) au lieu de `SubjectCoefficient`.
- `tests/Feature/Http/ReportCardPdfTest.php` : vérifier que le nom de matière primaire s'affiche correctement dans le détail du bulletin PDF (`subjectBreakdown`).

## Vérification

1. `php artisan migrate:fresh --seed`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean (attention particulière au type union `Subject|PrimarySubject` sur `SubjectAverage` et au retour de `coefficientsFor()`).
4. Vérification manuelle sur les données WAMP existantes (établissement "EPV LE PETIT PRINCE", préscolaire/primaire) : créer une matière primaire avec coefficient sur CP1 uniquement, vérifier qu'elle n'apparaît que pour une classe CP1 en saisie d'évaluation et pas pour une classe CM2.
5. Commit puis mise à jour de la mémoire projet si pertinent.
