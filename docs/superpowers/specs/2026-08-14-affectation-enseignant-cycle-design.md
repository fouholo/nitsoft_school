# Affectation enseignant : matière optionnelle au préscolaire/primaire

## Contexte

`TeacherAssignment` (table `teacher_classroom_subject`) exige aujourd'hui systématiquement une matière (`subject_id` NOT NULL), quel que soit le cycle de la classe. Or la réalité du terrain diffère selon le cycle :

- **Préscolaire/Primaire** : un seul enseignant généraliste est affecté à la classe entière et enseigne toutes les matières — l'affectation ne doit porter que sur la classe, sans matière précise.
- **Secondaire** : les enseignants sont spécialisés par matière — l'affectation doit rester liée à la classe **et** à la matière, comme aujourd'hui.

Ce chantier corrige `TeacherAssignment` et tous les points du code qui supposent implicitement qu'une affectation a toujours une matière. Aucune donnée existante n'est concernée (table vide en base au moment du chantier).

## 1. Schéma

Nouvelle migration `make_subject_id_nullable_on_teacher_classroom_subject_table` :

```php
Schema::table('teacher_classroom_subject', function (Blueprint $table): void {
    $table->foreignId('subject_id')->nullable()->change();
});
```

L'index unique existant `teacher_class_subject_unique (user_id, classroom_id, subject_id, school_year_id)` est conservé sans modification : il protège efficacement les doublons au secondaire. Au préscolaire/primaire, plusieurs lignes à `subject_id NULL` ne seraient pas bloquées par cet index (NULL ≠ NULL en SQL) — la protection contre les doublons "classe entière" repose sur `TeacherAssignment::firstOrCreate()` côté application (recherche incluant `subject_id => null`), même principe que la grille de coefficients (`SubjectCoefficient`, chantier du 2026-08-10).

## 2. Écran `Livewire\Academics\TeacherAssignments\Index`

Le formulaire devient cycle-conscient, sur le modèle de `updatedClassroomId()` déjà présent dans `Livewire\Grading\GradeSheets\Index` :

- `updatedClassroomId()` (nouveau) : réinitialise `subject_id` à `null` à chaque changement de classe.
- Validation dans `save()` : `subject_id` devient `required` uniquement si la classe sélectionnée est de cycle secondaire (`exists:subjects,id` sinon `nullable`), déterminé via `Classroom::findOrFail($data['classroom_id'])->level->cycle`.
- Vue (`resources/views/livewire/academics/teacher-assignments/index.blade.php`) : le champ "Matière" ne s'affiche que si la classe sélectionnée (`$classroom_id`) résout à un cycle secondaire — nécessite d'exposer le cycle de la classe sélectionnée au template (propriété ou méthode calculée `selectedClassroomCycle()`, même nom que dans `GradeSheets\Index` par cohérence).
- `TeacherAssignment::firstOrCreate($data)` inchangé — `$data['subject_id']` vaut `null` pour une classe préscolaire/primaire, la recherche `firstOrCreate` matche donc correctement les lignes existantes à `subject_id NULL`.
- Tableau des affectations : colonne "Matière" affiche `$assignment->subject?->name ?? '—'`.

## 3. Contrôle d'accès aux évaluations

Deux points supposent aujourd'hui qu'une affectation a toujours une matière et passent systématiquement `subject_id` à `User::isAssignedToClassroom()` (qui, elle, accepte déjà un `subjectId` optionnel — aucun changement necessaire sur cette méthode) :

- **`GradeSheets\Index::save()`** (ligne ~100) : remplace l'appel inconditionnel par une vérification cycle-consciente — pour une classe secondaire, `isAssignedToClassroom($classroomId, $subjectId)` comme aujourd'hui ; pour une classe préscolaire/primaire, `isAssignedToClassroom($classroomId)` sans matière (l'enseignant affecté à la classe entière peut créer une évaluation pour n'importe quelle matière de cette classe).
- **`GradeSheetPolicy::update()`** (ligne ~56) : même correction, basée sur `$gradeSheet->classroom->level->cycle` (la feuille de notes elle-même garde toujours sa matière — seule la vérification de l'affectation de l'enseignant change).

## 4. Liste de matières proposées à l'enseignant (`GradeSheets\Index::render()`)

Actuellement, pour un enseignant non-admin, la liste de matières du formulaire de création provient de `$assignments->pluck('subject')->unique('id')` — dérivée des matières de ses affectations. Avec des affectations "classe entière" (matière nulle), cette liste contiendrait des entrées nulles pour ces classes.

Correction : pour les classes où l'enseignant a une affectation "classe entière" (préscolaire/primaire), la liste de matières proposées devient toutes les matières compatibles avec ce cycle (`Subject::where('is_prescolaire_primaire', true)`, même logique que le filtrage par cycle déjà en place depuis le chantier du catalogue de matières global) — puisque l'enseignant peut noter n'importe quelle matière de cette classe. Pour les classes secondaires, le comportement actuel (matières de ses affectations spécifiques) est inchangé.

## Hors périmètre

- Pas de migration de données existantes (table `teacher_classroom_subject` vide).
- Pas de changement à `User::isAssignedToClassroom()` (déjà tolérant à un `subjectId` optionnel).
- Pas de changement au comportement de la présence (`AttendanceSessions`), déjà classe-seule sans matière pour tous les cycles — ce chantier ne touche que la Grading.
- Pas de contrainte unique en base robuste au niveau SQL pour le cas `subject_id NULL` — protection applicative uniquement, comme documenté en section 1.

## Tests à mettre à jour

- Nouveau `tests/Feature/Livewire/Academics/TeacherAssignmentsTest.php` (n'existe pas actuellement) : le champ matière est masqué/non exigé pour une classe primaire, exigé pour une classe secondaire ; `firstOrCreate` ne duplique pas une affectation "classe entière" existante.
- `tests/Feature/Policies/GradingAndAttendancePolicyTest.php` : nouveau cas — un enseignant avec une affectation "classe entière" (primaire) peut créer/gérer une évaluation sur n'importe quelle matière de cette classe.
- `tests/Feature/Livewire/Grading/GradeSheetsTest.php` : la liste de matières proposée à un enseignant affecté "classe entière" sur une classe primaire contient toutes les matières compatibles primaire, pas seulement celles de ses affectations explicites.
