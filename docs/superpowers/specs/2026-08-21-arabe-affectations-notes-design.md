# Filière arabe — sous-chantier 2 : affectations enseignants + saisie des notes — design

*Statut : approuvé par l'utilisateur le 2026-08-21.*

## Contexte

Suite du chantier « filière arabe » décomposé en 3 sous-chantiers indépendants (voir `2026-08-21-arabe-fondations-design.md`) :

1. Fondations arabe (catalogues, coefficients, écrans d'administration) — terminé, committé (`1cf6619`), migré sur la base réelle.
2. **Affectation des enseignants arabes + saisie des notes** (ce document).
3. Bulletins arabes (génération + PDF en écriture arabe RTL) — non planifié à ce stade.

Le sous-chantier 1 avait explicitement déferré `ArabicTerm` (les périodes arabes) à ce sous-chantier, faute de consommateur concret à l'époque — la saisie de notes en est le premier.

## Décisions validées

1. **Périodes arabes (`ArabicTerm`)** : un seul modèle unifié (pas de duplication `Term`/`composition_number` comme en français) — `label` et `sequence` toujours renseignés, `starts_on`/`ends_on` nullable (renseignés seulement si l'`ArabicLevel` lié est de cycle Secondaire-équivalent). Cohérent avec la simplification déjà actée pour `ArabicSubject` en sous-chantier 1.
2. **Groupement pour l'arabe** : indépendant de la classe française — les élèves sont regroupés pour l'affectation enseignant et la saisie de notes par `(arabic_level_id, arabic_serie_id)`, potentiellement à cheval sur plusieurs classes françaises. Cohérent avec le fait que `arabic_level_id`/`arabic_serie_id` sont portés par `Enrollment`, pas par `Classroom`.
3. **Affectation enseignant** : nouvelle table dédiée (pas d'extension de `teacher_classroom_subject`), pour éviter de mélanger deux domaines (`Subject` français / `ArabicSubject`) sur une même ligne.
4. **Référence des notes** : `ArabicGrade` référence `Enrollment` (pas `Student` comme le fait `Grade` côté français) — cohérent avec le fait que `arabic_level_id`/`arabic_serie_id` vivent déjà sur `Enrollment`, et fige le niveau/série arabe de l'élève au moment de la note.
5. **Accès à la saisie de notes** : même règle que le français (`GradeSheetPolicy`, `RolePermissions::MATRIX['grades.enter']`) — seul **educateur** a un accès large (toutes les grilles arabes de l'établissement), en plus de l'enseignant affecté qui ne voit que ses groupes. Correction faite en cours d'implémentation : fondateur/directeur/gestionnaire n'ont **pas** d'accès large à la saisie de notes côté français non plus (confirmé par `SecondaireTest.php` — « un directeur ne voit pas le lien Saisir les notes ») ; la description initiale de cette décision était inexacte sur ce point, l'intention réelle (« même accès que le français ») pointait déjà vers la règle `grades.enter` telle qu'elle existe.
6. **Hors périmètre** : génération/finalisation du bulletin arabe, calcul de moyennes, rendu RTL en PDF — repoussés au sous-chantier 3. Ce sous-chantier s'arrête au stockage des notes brutes.

## 1. Modèles (`App\Domain\Arabic\Models\`)

### `ArabicTerm`

Propre à chaque établissement × année scolaire — miroir simplifié de `Term`.

```php
Schema::create('arabic_terms', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
    $table->string('label');
    $table->date('starts_on')->nullable();
    $table->date('ends_on')->nullable();
    $table->unsignedTinyInteger('sequence');
    $table->string('uid_local', 20)->unique();
    $table->char('uid_serveur', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

`Syncable` (préfixe `252`) + `TenantScoped`, comme `Term`. `starts_on`/`ends_on` restent `null` pour un `ArabicTerm` utilisé par des niveaux Préscolaire/Primaire-équivalents — `sequence` fait alors office de numéro de composition.

### `ArabicTeacherAssignment`

```php
Schema::create('teacher_arabic_level_subject', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('arabic_level_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_serie_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
    $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
});
```

`TenantScoped` uniquement, pas `Syncable` — même choix que `TeacherAssignment` côté français (pas de suivi offline sur les affectations).

### `ArabicGradeSheet`

L'« évaluation » — miroir de `GradeSheet`, sans la dualité de colonnes du français puisque `ArabicSubject`/`ArabicTerm` sont déjà unifiés.

```php
Schema::create('arabic_grade_sheets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_level_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_serie_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_subject_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_term_id')->constrained()->cascadeOnDelete();
    $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
    $table->string('title');
    $table->string('type'); // 'devoir' | 'interrogation', même vocabulaire que GradeSheet
    $table->decimal('max_score', 6, 2);
    $table->decimal('weight', 4, 2);
    $table->date('graded_on');
    $table->string('uid_local', 20)->unique();
    $table->char('uid_serveur', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

`Syncable` (préfixe `253`) + `TenantScoped`, comme `GradeSheet`.

### `ArabicGrade`

```php
Schema::create('arabic_grades', function (Blueprint $table) {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('arabic_grade_sheet_id')->constrained()->cascadeOnDelete();
    $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
    $table->decimal('score', 5, 2)->nullable();
    $table->text('comment')->nullable();
    $table->string('uid_local', 20)->unique();
    $table->char('uid_serveur', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

`Syncable` (préfixe `254`) + `TenantScoped`, comme `Grade`.

Le roster d'une `ArabicGradeSheet` = tous les `Enrollment` de l'établissement × année scolaire dont `arabic_level_id`/`arabic_serie_id` correspondent à ceux de la grille, filtrés sur un statut actif — calculé dynamiquement à l'affichage de l'écran de saisie, pas de table de jointure classe.

## 2. Écrans

- **`Livewire\Arabic\Terms\Index`** (miroir de `Academics\Terms\Index`) : CRUD des périodes arabes par établissement × année scolaire courante. Réservé fondateur/directeur/gestionnaire.
- **`Livewire\Arabic\TeacherAssignments\Index`** (miroir de `Academics\TeacherAssignments\Index`) : formulaire enseignant + `arabic_level_id` + `arabic_serie_id` (affiché seulement si `arabic_level.requires_series`) + `arabic_subject_id` + `school_year_id`. Liste + suppression. Réservé fondateur/directeur/gestionnaire.
- **`Livewire\Arabic\GradeSheets\Index`** (miroir de `Grading\GradeSheets\Secondaire\Index`, un seul écran pour tous les cycles) : création d'une grille. Un enseignant ne voit/crée que sur les combinaisons niveau/série/matière où il est affecté ; fondateur/directeur/gestionnaire/educateur voient et créent partout dans l'établissement.
- **`Livewire\Arabic\GradeSheets\Enter`** (miroir de `Grading\GradeSheets\Enter`) : saisie des notes pour une grille donnée, roster = `Enrollment` correspondant au niveau/série de la grille.
- **Navigation** : trois nouvelles entrées dans le groupe « Arabe » existant (créé en sous-chantier 1) — « Périodes », « Affectations enseignants » et « Grilles de notes » — chacune gated par son ability `viewAny`.
- **Champs RTL** : les libellés de niveau/série/matière affichés (en-têtes, badges) gardent `dir="rtl"`. Le champ commentaire de note reste un texte libre sans direction imposée (l'enseignant peut y écrire en français ou en arabe).

## 3. Autorisations

Toutes les policies suivantes vérifient d'abord `Establishment::find(currentEstablishmentId)->is_arabe` pour `viewAny` (403 sinon, même pattern que `ArabicSubjectCoefficientPolicy`) :

- **`ArabicTermPolicy`** : `viewAny` = membre établissement `is_arabe` (pas de restriction de cycle, contrairement à `TermPolicy` qui exclut le préscolaire/primaire — `ArabicTerm` couvre tous les cycles via un seul modèle). `create`/`update`/`delete` = `in_array($user->currentRole(), ['fondateur','directeur','gestionnaire'], true)`.
- **`ArabicTeacherAssignmentPolicy`** : `viewAny` = membre établissement `is_arabe`. `create`/`delete` = `in_array($user->currentRole(), ['fondateur','directeur','gestionnaire'], true)` (pas d'élargissement educateur — action de configuration, même logique que les coefficients).
- **`ArabicGradeSheetPolicy`** : `viewAny`/`view` = membre établissement `is_arabe`, hors caissier (miroir exact de `GradeSheetPolicy`). `create` = `RolePermissions::can($user->currentRole(), 'grades.enter')` (educateur uniquement) **ou** l'utilisateur a une `ArabicTeacherAssignment` correspondant au `(arabic_level_id, arabic_serie_id, arabic_subject_id)` visé. `update` (saisie des notes via `Enter`) : même règle, vérifiée sur la grille existante (enseignant affecté ET auteur de la grille).

Toutes les policies utilisent `in_array($user->currentRole(), [...], true)` directement plutôt que `isAdminOfCurrentEstablishment()`/`hasAdminRightsOn()`, pour éviter la lacune connue (fondateur d'établissement indépendant sans Foundation) — voir mémoire `has_admin_rights_foundation_only_fondateur_gap`.

## 4. Tests

- **Domaine** (`ArabicGradingTest.php`) : casts/relations des 4 nouveaux modèles ; roster dynamique par `(arabic_level_id, arabic_serie_id)` (élèves de classes françaises différentes regroupés correctement) ; `ArabicGrade::updateOrCreate` idempotent ; `ArabicTerm.starts_on/ends_on` restent nuls pour un niveau non-Secondaire.
- **Livewire** : un test par écran (`Terms`, `TeacherAssignments`, `GradeSheets\Index`, `GradeSheets\Enter`) couvrant accès admin, accès enseignant limité à ses affectations, 403 pour un établissement `is_arabe = false`, et le cas fondateur-établissement-indépendant (régression connue) sur au moins un des écrans de configuration.
- **Isolation** : les grilles/notes d'un établissement A restent invisibles depuis l'établissement B, alors que le catalogue `ArabicLevel`/`ArabicSerie`/`ArabicSubject` reste partagé.

## Hors périmètre (pour l'instant)

- `ArabicReportCard`, calcul de moyennes/rangs, génération PDF, rendu RTL en PDF, police arabe pour dompdf — sous-chantier 3.
- Présences spécifiques à la filière arabe — non planifié à ce stade.
- Appréciations/barèmes d'appréciation arabes (équivalent `AppreciationScale`) — non demandé, à ajouter plus tard si le besoin se confirme.
