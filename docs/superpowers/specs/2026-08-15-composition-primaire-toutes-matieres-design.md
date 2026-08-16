# Composition primaire : une évaluation couvre toutes les matières du niveau

*(Design validé par l'utilisateur le 2026-08-15, plusieurs points confirmés par questions ciblées — cf. section Décisions.)*

## Contexte

Depuis les chantiers précédents de la journée, une évaluation (`GradeSheet`) primaire reste rattachée à **une seule matière** (`primary_subject_id`) — pour noter les 5 matières d'une composition, il faut créer 5 évaluations et saisir les notes 5 fois. L'utilisateur veut qu'une composition primaire devienne **une seule évaluation couvrant toutes les matières du niveau à la fois**, avec un écran de saisie **par élève** (pas par matière) affichant, pour un élève donné : son identité, un champ de note par matière, et un cadre de résultats calculés en direct (total des points, total des coefficients, moyenne) plus une appréciation générale.

## Décisions validées (questions posées, une à la fois)

1. **Écran de saisie = un élève à la fois**, avec en-tête identité (nom, classe), champs de note par matière, puis un cadre résultats (totaux, moyenne, appréciation).
2. **Le cadre résultats est un aperçu en direct**, recalculé à partir des notes déjà saisies — la génération officielle du bulletin (rang figé, `ReportCard.average`) reste un acte séparé via l'écran "Bulletins", inchangé.
3. **L'appréciation est enregistrée** dès la saisie et reportée sur le bulletin officiel généré — nouveau champ persistant.
4. **Une étape de création de la composition reste nécessaire** : classe + n° de composition + date (plus de champ matière/barème/coefficient à ce niveau).
5. **Le barème se configure par niveau sur `PrimarySubject`**, comme le coefficient — pas ressaisi à chaque composition.
6. **Le commentaire par matière de `PrimaryGrade` est supprimé**, au profit de la seule appréciation générale par élève et par composition.

Aucune donnée réelle de composition primaire n'existe (les quelques lignes de test créées lors des vérifications manuelles des chantiers précédents ne sont pas des données utilisateur — ignorées, pas de backfill).

## Partie 1 — `PrimarySubject` : barème par niveau

### Migration `add_bareme_to_primary_subjects_table`

```php
Schema::table('primary_subjects', function (Blueprint $table): void {
    $table->decimal('bareme_cp1', 5, 2)->nullable()->after('coefficient_cm2');
    $table->decimal('bareme_cp2', 5, 2)->nullable()->after('bareme_cp1');
    $table->decimal('bareme_ce1', 5, 2)->nullable()->after('bareme_cp2');
    $table->decimal('bareme_ce2', 5, 2)->nullable()->after('bareme_ce1');
    $table->decimal('bareme_cm1', 5, 2)->nullable()->after('bareme_ce2');
    $table->decimal('bareme_cm2', 5, 2)->nullable()->after('bareme_cm1');
});
```
(`decimal(5,2)`, même précision que `GradeSheet.max_score` — jusqu'à 999.99 — plutôt que `decimal(4,2)` des colonnes de coefficient, dont la plage plus faible convient à un coefficient mais pas à un barème.)

### `App\Domain\Academics\Models\PrimarySubject`

- `$fillable` += les 6 colonnes `bareme_*`. `$casts` += chacune en `decimal:2`.
- Factorise le mapping niveau → suffixe (déjà dupliqué dans `coefficientColumn()`) :
  ```php
  private static function levelSuffix(Level $level): string
  {
      return match ($level->level) {
          'CP1' => 'cp1', 'CP2' => 'cp2', 'CE1' => 'ce1',
          'CE2' => 'ce2', 'CM1' => 'cm1', 'CM2' => 'cm2',
          default => throw new \InvalidArgumentException("Niveau primaire inconnu : {$level->level}"),
      };
  }

  public static function coefficientColumn(Level $level): string
  {
      return 'coefficient_'.self::levelSuffix($level);
  }

  public static function baremeColumn(Level $level): string
  {
      return 'bareme_'.self::levelSuffix($level);
  }

  public function bareme(Level $level): ?float
  {
      $value = $this->{self::baremeColumn($level)};

      return $value !== null ? (float) $value : null;
  }
  ```
  (`coefficientFor()` existant inchangé, réutilise `coefficientColumn()`.)

### Écran `Academics\PrimarySubjects\Index`

Ajoute les 6 propriétés `bareme_cp1`…`bareme_cm2` (mêmes règles de validation que les coefficients : `nullable`, `numeric`, `min:0.5`), au formulaire (6 champs de plus) et à la grille (6 colonnes de plus, ou une présentation compacte "Coef / Barème" par niveau plutôt que 12 colonnes séparées — au choix de l'implémentation, en gardant la lisibilité).

## Partie 2 — `GradeSheet` primaire devient sans matière

### Migration `fix_primary_grades_unique_index`

**Bug critique à corriger avant tout** : l'index unique actuel sur `primary_grades` est `(grade_sheet_id, student_id)` — hérité du modèle "un GradeSheet = une matière". Avec une composition couvrant toutes les matières, plusieurs `PrimaryGrade` (une par matière) partagent désormais le même `(grade_sheet_id, student_id)` : l'insertion de la deuxième matière échouerait. Remplacer par `(grade_sheet_id, student_id, primary_subject_id)`.

```php
Schema::table('primary_grades', function (Blueprint $table): void {
    $table->dropUnique(['grade_sheet_id', 'student_id']);
    $table->unique(['grade_sheet_id', 'student_id', 'primary_subject_id']);
});
```

### Migration `drop_comment_from_primary_grades_table`

```php
Schema::table('primary_grades', function (Blueprint $table): void {
    $table->dropColumn('comment');
});
```
`PrimaryGrade::$fillable` : retire `comment`.

### `GradeSheets\Primaire\Index`

Formulaire de création réduit : `classroom_id`, `composition_number`, `title`, `graded_on` — plus de `primary_subject_id`, `max_score`, `weight` (ces deux dernières colonnes restent en base avec leur valeur par défaut, simplement non exposées/non pertinentes pour le primaire désormais).

- `save()` : valide les 4 champs restants, vérifie le cycle/l'affectation/`isGradable()` (inchangé), crée le `GradeSheet` sans `primary_subject_id`. Plus de vérification de coefficient à la création (elle n'a plus de sens : la composition ne cible plus une matière précise).
- `updatedClassroomId()` : ne reset plus que `composition_number`.
- `render()` : `gradeSheets` sans eager-load `primarySubject` ; plus de calcul de `subjects`/`isAssignedToClassroom` (déplacé dans les nouveaux écrans ci-dessous).
- Vue : table des compositions sans colonne "Matière" ; le lien "Saisir les notes" pointe vers la nouvelle route `grading.grade-sheets.primaire-students` (liste des élèves) au lieu de `grading.grade-sheets.enter`.

### Nouveau `GradeSheets\Primaire\Students` (liste des élèves d'une composition)

```php
class Students extends Component
{
    public GradeSheet $gradeSheet;

    public function mount(GradeSheet $gradeSheet): void
    {
        $this->authorize('update', $gradeSheet);
        abort_unless($gradeSheet->classroom->level->cycle === Cycle::Primaire, 404);
        $this->gradeSheet = $gradeSheet;
    }

    public function render()
    {
        $students = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('classroom_id', $this->gradeSheet->classroom_id)->where('status', 'active'))
            ->orderBy('last_name')
            ->get();

        return view('livewire.grading.grade-sheets.primaire.students', ['students' => $students]);
    }
}
```
Vue : titre + classe, liste des élèves avec un lien "Saisir les notes" par élève vers la route `grading.grade-sheets.primaire-enter-student`.

### Nouveau `GradeSheets\Primaire\EnterStudent` (écran de saisie par élève)

```php
class EnterStudent extends Component
{
    public GradeSheet $gradeSheet;
    public Student $student;

    /** @var array<int, string> */
    public array $scores = [];

    public string $appreciation = '';

    public bool $justSaved = false;

    public function mount(GradeSheet $gradeSheet, Student $student): void
    {
        $this->authorize('update', $gradeSheet);

        $this->gradeSheet = $gradeSheet;
        $this->student = $student;

        $existingGrades = PrimaryGrade::query()
            ->where('grade_sheet_id', $gradeSheet->id)
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('primary_subject_id');

        foreach ($this->subjects() as $subject) {
            $grade = $existingGrades->get($subject->id);
            $this->scores[$subject->id] = $grade?->score !== null ? (string) $grade->score : '';
        }

        $reportCard = ReportCard::query()
            ->where('student_id', $student->id)
            ->where('school_year_id', $gradeSheet->classroom->school_year_id)
            ->where('composition_number', $gradeSheet->composition_number)
            ->first();

        $this->appreciation = $reportCard->appreciation ?? '';
    }

    /** @return Collection<int, PrimarySubject> */
    private function subjects(): Collection
    {
        $column = PrimarySubject::coefficientColumn($this->gradeSheet->classroom->level);

        return PrimarySubject::whereNotNull($column)->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->authorize('update', $this->gradeSheet);

        $rules = ['appreciation' => ['nullable', 'string', 'max:1000']];
        foreach (array_keys($this->scores) as $subjectId) {
            $rules["scores.{$subjectId}"] = ['nullable', 'numeric', 'min:0'];
        }

        $this->validate($rules);

        foreach ($this->scores as $subjectId => $score) {
            PrimaryGrade::updateOrCreate(
                ['grade_sheet_id' => $this->gradeSheet->id, 'student_id' => $this->student->id, 'primary_subject_id' => $subjectId],
                ['score' => $score !== '' ? $score : null]
            );
        }

        ReportCard::updateOrCreate(
            [
                'student_id' => $this->student->id,
                'school_year_id' => $this->gradeSheet->classroom->school_year_id,
                'composition_number' => $this->gradeSheet->composition_number,
            ],
            [
                'establishment_id' => $this->gradeSheet->establishment_id,
                'classroom_id' => $this->gradeSheet->classroom_id,
                'appreciation' => $this->appreciation !== '' ? $this->appreciation : null,
            ]
        );

        $this->justSaved = true;
    }

    /** Aperçu en direct — mêmes formules que ReportCardService, non persisté. */
    private function preview(): array
    {
        $level = $this->gradeSheet->classroom->level;
        $totalPoints = 0.0;
        $totalCoefficient = 0.0;

        foreach ($this->subjects() as $subject) {
            $score = $this->scores[$subject->id] ?? '';
            if ($score === '') {
                continue;
            }

            $coefficient = $subject->coefficientFor($level) ?? 0.0;
            $bareme = $subject->bareme($level) ?? 20.0;
            $normalized = ((float) $score / $bareme) * 20;

            $totalPoints += $normalized * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return [
            'totalPoints' => round($totalPoints, 2),
            'totalCoefficient' => $totalCoefficient,
            'average' => $totalCoefficient > 0 ? round($totalPoints / $totalCoefficient, 2) : null,
        ];
    }

    public function render()
    {
        return view('livewire.grading.grade-sheets.primaire.enter-student', [
            'subjects' => $this->subjects(),
            'preview' => $this->preview(),
        ]);
    }
}
```

`updated` sur `scores.*` et `appreciation` n'est pas nécessaire : Livewire re-render à chaque `wire:model.live`, donc `render()` (et son `preview()`) se recalcule automatiquement à chaque frappe si les champs de note utilisent `wire:model.live`.

### Routes (`routes/grading.php`)

```php
Route::get('/grade-sheets', GradeSheetsIndex::class)->name('grade-sheets.index');
Route::get('/grade-sheets/{gradeSheet}/enter', GradeSheetsEnter::class)->name('grade-sheets.enter'); // secondaire uniquement désormais
Route::get('/grade-sheets/{gradeSheet}/students', PrimaireStudents::class)->name('grade-sheets.primaire-students');
Route::get('/grade-sheets/{gradeSheet}/students/{student}', PrimaireEnterStudent::class)->name('grade-sheets.primaire-enter-student');
```

### `App\Livewire\Grading\GradeSheets\Enter` (écran existant) : redevient secondaire uniquement

Retire toute la logique `isPrimaire()`/`PrimaryGrade` ajoutée au chantier précédent — plus aucun `GradeSheet` primaire ne peut désormais atteindre cet écran (le lien "Saisir les notes" des compositions primaire pointe vers le nouveau flux à deux écrans). Revient à sa forme d'avant le chantier "table de notes dédiée au primaire" : lit/écrit uniquement `Grade`.

## Partie 3 — `ReportCard` : appréciation

### Migration `add_appreciation_to_report_cards_table`

```php
Schema::table('report_cards', function (Blueprint $table): void {
    $table->text('appreciation')->nullable()->after('rank');
});
```
`ReportCard::$fillable` += `appreciation`.

### `ReportCards\Primaire\Index::render()`

Filtre la liste des bulletins sur `whereNotNull('average')` : un `ReportCard` créé tôt (appréciation seule, saisie en cours) ne doit pas apparaître comme un bulletin déjà généré.

### PDF (`resources/views/pdf/report-card.blade.php`)

Ajoute, si renseignée, une ligne affichant `$reportCard->appreciation`.

## Partie 4 — `ReportCardService` : barème par matière/niveau pour le primaire

`weightedAverage()` normalisait jusqu'ici chaque note via `$grade->gradeSheet->max_score` (valable pour le secondaire, où le barème est propre à l'évaluation). Pour le primaire, le barème vient désormais de `PrimarySubject.bareme_*` selon le niveau de la classe — nécessite le niveau de la classe, pas seulement la note.

```php
private function maxScoreFor(Grade|PrimaryGrade $grade): float
{
    if ($grade instanceof PrimaryGrade) {
        return $grade->primarySubject->bareme($grade->gradeSheet->classroom->level) ?? 20.0;
    }

    return (float) $grade->gradeSheet->max_score;
}
```
`weightedAverage()` : remplace `(float) $grade->gradeSheet->max_score` par `$this->maxScoreFor($grade)` dans le calcul de `$weightedSum`.

Eager loading étendu dans `generate()`/`subjectBreakdown()` côté primaire : `PrimaryGrade::query()->with(['gradeSheet.classroom.level', 'primarySubject'])` (au lieu de `gradeSheet.primarySubject` seul).

Le reste (`coefficientsFor`/`primaryCoefficientsFor`, `assertCoefficientsConfigured`, `generalAverage`, `subjectFor`, `subjectKeyFor`) est inchangé — ces méthodes ne dépendaient pas du barème.

## Tests à écrire/adapter

- `PrimarySubjectsTest.php` : coefficient + barème enregistrés ensemble.
- `GradeSheets\Primaire\PrimaireTest.php` : entièrement réécrit — la création ne porte plus de matière ; les tests de rejet de matière incompatible disparaissent.
- Nouveaux `tests/Feature/Livewire/Grading/GradeSheets/Primaire/StudentsTest.php` et `.../EnterStudentTest.php` : liste des élèves, saisie multi-matières pour un élève, aperçu en direct correct, appréciation enregistrée et reportée sur le `ReportCard` (y compris qu'une régénération officielle ultérieure ne l'efface pas), notes déjà saisies préchargées.
- `ReportCards\Primaire\Test.php` : les fixtures créent désormais plusieurs `PrimaryGrade` (une par matière) sous un seul `GradeSheet` par composition ; la liste ne montre que les bulletins avec `average` renseignée.
- `ReportCardServiceTest.php` : le test de composition primaire utilise le barème par niveau (`PrimarySubject::factory()->create([..., $baremeColumn => 20])`) plutôt qu'un `max_score` sur `GradeSheet`.
- `tests/Feature/Http/ReportCardPdfTest.php` : nouveau test vérifiant l'affichage de l'appréciation si renseignée.

## Vérification

1. `php artisan migrate`.
2. `vendor/bin/pest` — suite complète verte.
3. `vendor/bin/phpstan analyse --memory-limit=512M` — clean.
4. Vérification manuelle sur les données WAMP existantes (établissement "EPV LE PETIT PRINCE") : créer une composition CP1, saisir les notes d'un élève sur plusieurs matières via le nouvel écran, vérifier l'aperçu en direct (total/moyenne cohérents avec le barème et le coefficient de chaque matière), enregistrer une appréciation, puis générer officiellement les bulletins et vérifier que l'appréciation déjà saisie apparaît sur le PDF.
