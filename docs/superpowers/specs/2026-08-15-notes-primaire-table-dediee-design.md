# Table de notes dédiée au primaire (`PrimaryGrade`)

*(Design validé par l'utilisateur le 2026-08-15, dans la continuité du chantier "Catalogue de matières du primaire" — cf. `2026-08-15-matieres-primaire-catalogue-coefficients-design.md`.)*

## Contexte

Suite logique du catalogue `PrimarySubject` : la table `grades` (modèle `Grade`) sert aujourd'hui indifféremment les deux cycles, chaque ligne étant rattachée à une `GradeSheet` (qui porte déjà `subject_id` XOR `primary_subject_id` selon le cycle). L'utilisateur souhaite une table de notes dédiée au primaire, avec un lien direct vers la matière en plus du lien vers l'évaluation. Trois décisions validées avec l'utilisateur :

1. `PrimaryGrade` **remplace** `Grade` pour le primaire (même logique que `PrimarySubject`/`Subject`) — `grades` redevient secondaire uniquement.
2. Mécanisme de synchronisation identique à celui déjà utilisé partout (`Syncable` : `uid_local`/`uid_serveur`/`device_id`/`client_updated_at`) — pas de nouveau système de statut de synchro.
3. `primary_subject_id` est porté **directement** par la note (redondant avec `grade_sheet.primary_subject_id`), pas seulement dérivé de l'évaluation.
4. Le champ `comment` (appréciation optionnelle) de `Grade` est conservé sur `PrimaryGrade`.

Aucune donnée réelle de note primaire n'existe en base (vérifié en amont du chantier précédent : 0 `GradeSheet` avec `composition_number` renseigné) — pas de backfill nécessaire.

## Partie 1 — Migration et modèle

### Migration `create_primary_grades_table`

```php
Schema::create('primary_grades', function (Blueprint $table): void {
    $table->id();
    $table->string('uid_local', 20)->unique();
    $table->char('uid_serveur', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();

    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('grade_sheet_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained()->cascadeOnDelete();
    $table->foreignId('primary_subject_id')->constrained()->cascadeOnDelete();
    $table->decimal('score', 5, 2)->nullable();
    $table->string('comment')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->unique(['grade_sheet_id', 'student_id']);
    $table->index('establishment_id');
});
```

Nouvelle entrée `uid_server_counters` (préfixe libre suivant, vérifié contre tous les préfixes existants) : `'225'`.

### `App\Domain\Grading\Models\PrimaryGrade`

Calqué sur `Grade` : `HasFactory`, `SoftDeletes`, `Syncable`, `TenantScoped`. `$fillable` : `establishment_id`, `grade_sheet_id`, `student_id`, `primary_subject_id`, `score`, `comment`, + colonnes de synchro. `$casts` : `score` → `decimal:2`, `client_updated_at` → `datetime`. Relations `gradeSheet(): BelongsTo`, `student(): BelongsTo`, `primarySubject(): BelongsTo`.

### `App\Domain\Grading\Models\GradeSheet`

Nouvelle relation `primaryGrades(): HasMany` → `hasMany(PrimaryGrade::class)`, symétrique de `grades(): HasMany`.

### Factory `PrimaryGradeFactory`

Calquée sur `GradeFactory`, avec `primary_subject_id => PrimarySubject::factory()` en plus.

## Partie 2 — Écran de saisie (`GradeSheets\Enter`)

Reste un écran unique (pas de scission Primaire/Secondaire — le formulaire est identique entre les deux cycles, seule la table de destination change). Nouvelle méthode privée :

```php
private function isPrimaire(): bool
{
    return $this->gradeSheet->primary_subject_id !== null;
}
```

- `mount()` : charge les notes existantes depuis `$this->gradeSheet->primaryGrades()` si `isPrimaire()`, sinon `$this->gradeSheet->grades()` (inchangé sinon).
- `save()` : pour chaque étudiant, si `isPrimaire()` → `PrimaryGrade::updateOrCreate(['grade_sheet_id' => ..., 'student_id' => ...], ['score' => ..., 'comment' => ..., 'primary_subject_id' => $this->gradeSheet->primary_subject_id])` ; sinon `Grade::updateOrCreate(...)` (comportement actuel inchangé).
- Vue (`enter.blade.php`) : déjà corrigée au chantier précédent pour afficher `$gradeSheet->subject?->name ?? $gradeSheet->primarySubject?->name` — aucun autre changement de template nécessaire (formulaire score/appréciation identique).

## Partie 3 — `ReportCardService`

Les méthodes de calcul (`weightedAverage`, `subjectFor`, `subjectKeyFor`, `generalAverage`) deviennent génériques sur `Grade|PrimaryGrade` (même traitement que `Subject|PrimarySubject` sur `SubjectAverage` au chantier précédent) — aucune logique de calcul ne change, seul le type accepté s'élargit.

Nouvelle méthode privée de sélection de la source, utilisée par `generate()` et `subjectBreakdown()` :

```php
private function gradesQuery(Classroom $classroom): Builder
{
    return $classroom->level->cycle === Cycle::Primaire
        ? PrimaryGrade::query()->with(['gradeSheet.primarySubject'])
        : Grade::query()->with(['gradeSheet.subject']);
}
```

`generate()` : remplace `Grade::query()->whereNotNull('score')->whereHas('gradeSheet', ...)->with(...)->get()` par `$this->gradesQuery($classroom)->whereNotNull('score')->whereHas('gradeSheet', ...)->get()`.

`subjectBreakdown(ReportCard $reportCard)` : même substitution, basée sur `$reportCard->classroom->level->cycle`.

## Partie 4 — Portail parent (`GuardianPortal\StudentGrades`)

Un élève peut avoir des notes des deux cycles au fil de sa scolarité (progression préscolaire/primaire → secondaire) — fusion des deux sources plutôt que branchement exclusif :

```php
'grades' => Grade::query()
    ->where('student_id', $this->student->id)
    ->whereNotNull('score')
    ->with(['gradeSheet.subject', 'gradeSheet.term'])
    ->get()
    ->concat(
        PrimaryGrade::query()
            ->where('student_id', $this->student->id)
            ->whereNotNull('score')
            ->with(['gradeSheet.primarySubject'])
            ->get()
    ),
```

Vue (`student-grades.blade.php`) : déjà corrigée au chantier précédent (`$grade->gradeSheet?->subject?->name ?? $grade->gradeSheet?->primarySubject?->name`) — fonctionne indifféremment pour une ligne `Grade` ou `PrimaryGrade` puisque les deux exposent les mêmes relations utilisées par la vue (`gradeSheet`, `student`).

## Tests à écrire/adapter

- Nouveau `tests/Feature/Livewire/Grading/EnterTest.php` : scénarios primaire (saisie/relecture d'une note via `PrimaryGrade`, appréciation conservée) en plus des scénarios secondaire existants.
- `tests/Feature/Livewire/Grading/ReportCards/PrimaireTest.php` et `tests/Feature/Domain/ReportCardServiceTest.php` : les fixtures `Grade::factory()->create([...])` pour les scénarios primaire deviennent `PrimaryGrade::factory()->create([..., 'primary_subject_id' => $subject->id])`.
- Nouveau test portail parent : un élève avec des notes `Grade` et `PrimaryGrade` voit les deux dans son relevé.

## Vérification

1. `php artisan migrate`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur les données WAMP existantes : saisir une note primaire via l'écran "Saisir les notes" pour la matière de test créée au chantier précédent, vérifier qu'elle apparaît bien dans `primary_grades` et non `grades`, puis régénérer le bulletin correspondant.
