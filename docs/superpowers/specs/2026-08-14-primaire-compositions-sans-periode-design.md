# Primaire : compositions sans notion de période

## Contexte

Aujourd'hui, une évaluation (`GradeSheet`) et un bulletin (`ReportCard`) sont systématiquement rattachés à un `Term` (« période » — libellé, date de début/fin, séquence), quel que soit le cycle. Depuis le chantier du 2026-08-10 (`docs/superpowers/specs/2026-08-10-evaluations-primaire-secondaire-design.md`), le primaire utilise déjà un `Term` dont le libellé est conventionnellement « Composition 1/2/3 » (placeholder ajusté selon `Establishment::type`), mais la structure reste identique au secondaire : il faut créer administrativement une période (avec dates de début/fin) avant de pouvoir saisir des notes.

Or, au primaire, la notion de « période » n'a pas de sens propre : à chaque composition, chaque matière du niveau reçoit une note (une seule, pas plusieurs devoirs/interrogations à pondérer comme au secondaire). Ce chantier supprime la notion de période du modèle de données pour le primaire/préscolaire et la remplace par un simple numéro de composition porté directement par l'évaluation et le bulletin.

- **Secondaire** : inchangé — `Term` (périodes avec dates), évaluations multiples par matière et par période, moyenne pondérée.
- **Préscolaire/Primaire** : plus de `Term` du tout — un numéro de composition (1, 2, 3...) saisi directement sur l'évaluation et le bulletin.

`EstablishmentType` (`PrescolairePrimaire` | `Secondaire`) déterminant un établissement entier (un établissement n'a jamais de classes mélangeant les deux types), la bascule se fait de la même façon que dans `Academics\Terms\Index::labelPlaceholder()` — par type d'établissement — sauf dans les écrans déjà organisés par classe (`GradeSheets\Index`), où le cycle de la classe sélectionnée (`selectedClassroomCycle()`, déjà en place) reste le point de bascule le plus précis et cohérent avec l'existant.

## 1. Schéma

### `grade_sheets`

Nouvelle migration :

```php
Schema::table('grade_sheets', function (Blueprint $table): void {
    $table->foreignId('term_id')->nullable()->change();
    $table->unsignedTinyInteger('composition_number')->nullable()->after('term_id');
});
```

Pas de contrainte unique DB sur `composition_number` (ni sur `term_id` aujourd'hui) — même principe que documenté dans `2026-08-14-affectation-enseignant-cycle-design.md` et confirmé par la correction du jour sur `installments` (une contrainte unique sur une table avec soft-delete bloque la réutilisation d'une valeur après suppression). L'unicité — une seule note par matière et par composition au primaire — n'est de toute façon pas garantie aujourd'hui côté secondaire non plus (plusieurs devoirs par matière et par période sont autorisés) ; elle n'est donc pas introduite ici.

### `report_cards`

Nouvelle migration :

```php
Schema::table('report_cards', function (Blueprint $table): void {
    $table->foreignId('term_id')->nullable()->change();
    $table->foreignId('school_year_id')->nullable()->after('term_id')->constrained()->cascadeOnDelete();
    $table->unsignedTinyInteger('composition_number')->nullable()->after('school_year_id');

    $table->unique(['student_id', 'school_year_id', 'composition_number']);
});
```

`school_year_id` est ajouté malgré sa redondance avec `classroom_id` (une classe appartient à une seule année scolaire) : c'est le moyen le plus simple d'obtenir une contrainte unique fiable en base pour le couple (élève, composition) sans dépendre d'une jointure. Comme les lignes secondaire ont `composition_number` à `NULL`, l'index unique existant `(student_id, term_id)` continue de les protéger contre les doublons ; le nouvel index protège symétriquement les lignes primaire (`term_id` à `NULL`). SQL traite `NULL` comme distinct de lui-même dans un index unique, donc les deux familles de lignes ne s'interfèrent jamais.

### Modèles

- `GradeSheet` : `$fillable` += `composition_number` ; `term_id` reste dans `$fillable` (nullable maintenant) ; relation `term()` reste `BelongsTo` (devient optionnelle en pratique).
- `ReportCard` : `$fillable` += `school_year_id`, `composition_number`.

Règle appliquée uniquement côté application (Livewire), pas en base : une ligne a soit `term_id` renseigné (secondaire), soit `composition_number` renseigné (primaire/préscolaire), jamais les deux, jamais aucun des deux.

## 2. Écran « Périodes » (`Livewire\Academics\Terms\Index`)

Devient inaccessible pour un établissement de type `PrescolairePrimaire` :

- `mount()` : `abort_if(Establishment::findOrFail(...)->type === EstablishmentType::PrescolairePrimaire, 403)`.
- Nav (`resources/views/layouts/app.blade.php`) : l'entrée « Périodes » n'est affichée que si l'établissement courant est de type `Secondaire`.

Les enregistrements `Term` déjà créés pour des établissements préscolaire/primaire existants (le cas échéant) ne sont pas supprimés — l'écran devient simplement inaccessible, cohérent avec le principe déjà appliqué ailleurs (pas de migration de données destructive sans nécessité).

## 3. Écran « Évaluations » (`Livewire\Grading\GradeSheets\Index`)

- Nouvelle propriété publique `?int $composition_number = null`.
- `updatedClassroomId()` (existant, ligne ~59) : réinitialise aussi `composition_number` à `null`.
- `save()` (existant, ligne ~86) : validation conditionnelle selon `Classroom::findOrFail($data['classroom_id'])->level->cycle` —
  - `Cycle::Secondaire` : `term_id` requis (`exists:terms,id`), `composition_number` absent de la validation (forcé à `null` avant `create()`).
  - `Cycle::Primaire` : `composition_number` requis (`integer`, `min:1`, `max:10`), `term_id` absent de la validation (forcé à `null` avant `create()`).
- Vue (`resources/views/livewire/grading/grade-sheets/index.blade.php`) : le champ « Période » (select) devient conditionnel au cycle secondaire ; un champ numérique « N° de composition » apparaît à la place pour le cycle primaire, sur le modèle du conditionnement déjà en place pour le champ « Matière » dans `TeacherAssignments\Index`.
- Tableau des évaluations (`render()`, ligne ~170) : colonne « Période » affiche `$gradeSheet->term?->label ?? "Composition {$gradeSheet->composition_number}"`.

## 4. Écran « Bulletins » (`Livewire\Grading\ReportCards\Index`)

- Remplace la propriété unique `?int $term_id` par deux propriétés : `?int $term_id` (secondaire) et `?int $composition_number` (primaire), plus `selectedClassroomCycle()` (même helper que `GradeSheets\Index`, dupliqué ici par cohérence avec le pattern existant plutôt que factorisé — chaque écran Livewire du domaine Grading porte déjà sa propre copie).
- `generate()` : appelle `generateForClassroomAndTerm()` ou `generateForClassroomAndComposition()` selon le cycle de la classe sélectionnée.
- `render()` : la requête de listing des bulletins filtre par `term_id` ou par `(school_year_id, composition_number)` selon le cas ; `schoolYearId` récupéré via `$classroom->school_year_id` quand on est en mode composition.
- Vue : même bascule de champ que dans `GradeSheets\Index`.

## 5. `ReportCardService`

Nouvelle méthode, miroir de `generateForClassroomAndTerm()` :

```php
public function generateForClassroomAndComposition(Classroom $classroom, int $compositionNumber): Collection
```

- Même logique (moyenne générale, rang, `assertCoefficientsConfigured`), la clause `whereHas('gradeSheet', ...)` filtrant sur `classroom_id` + `composition_number` au lieu de `classroom_id` + `term_id`.
- `ReportCard::updateOrCreate` avec la clé `['student_id' => $studentId, 'school_year_id' => $classroom->school_year_id, 'composition_number' => $compositionNumber]`.
- `subjectBreakdown(ReportCard $reportCard)` : la clause de filtrage des notes se base sur `$reportCard->term_id !== null ? ['term_id' => $reportCard->term_id] : ['composition_number' => $reportCard->composition_number]`.

Le calcul de moyenne (`weightedAverage`, `generalAverage`) ne change pas : il opère déjà au niveau des `Grade`/`GradeSheet` récupérés, indépendamment de la façon dont ceux-ci ont été filtrés en amont.

## 6. PDF du bulletin (`resources/views/pdf/report-card.blade.php`)

Ligne ~29, actuellement :

```blade
<p>Bulletin — {{ $reportCard->term->label }} ({{ $reportCard->term->schoolYear?->label }})</p>
```

Devient :

```blade
<p>Bulletin — {{ $reportCard->term?->label ?? "Composition {$reportCard->composition_number}" }} ({{ ($reportCard->term?->schoolYear ?? $reportCard->classroom->schoolYear)?->label }})</p>
```

Nécessite que le contrôleur (`ReportCardPdfController`) charge `classroom.schoolYear` en plus des relations déjà chargées (`term.schoolYear` reste chargé pour le cas secondaire).

## Hors périmètre

- Pas de migration des `Term` déjà créés pour des établissements préscolaire/primaire existants — l'écran devient inaccessible, les lignes restent en base (aucune donnée réelle concernée à ce jour, comme vérifié lors du chantier terminologie du 2026-08-14).
- Pas de contrainte DB empêchant deux `GradeSheet` pour la même matière/composition/classe (cohérent avec l'absence de contrainte équivalente au secondaire).
- Pas de changement à `GradeSheetPolicy` ni à la vérification d'affectation enseignant (`isAssignedToClassroom`) — ce chantier ne touche que le rattachement période/composition, pas le contrôle d'accès.
- Pas de renommage de `Term`/« Périodes » en base ou dans le code — le modèle et l'écran restent nommés ainsi, simplement non exposés aux établissements préscolaire/primaire.

## Tests à mettre à jour

- `tests/Feature/Livewire/Academics/TermsTest.php` : nouveau cas — l'écran est inaccessible (403) pour un établissement `PrescolairePrimaire`.
- `tests/Feature/Livewire/Grading/GradeSheetsTest.php` : création d'une évaluation primaire avec `composition_number` (sans `term_id`) ; validation rejette `composition_number` manquant au primaire et `term_id` manquant au secondaire.
- `tests/Feature/Livewire/Grading/ReportCardsTest.php` : génération de bulletins primaire par `composition_number`, unicité par élève/année/composition.
- `tests/Feature/Domain/ReportCardServiceTest.php` : `generateForClassroomAndComposition()` calcule moyenne/rang identiquement à `generateForClassroomAndTerm()`.
- `tests/Feature/Http/ReportCardPdfTest.php` : le PDF affiche « Composition N » et l'année scolaire correcte pour un bulletin primaire sans `term_id`.
