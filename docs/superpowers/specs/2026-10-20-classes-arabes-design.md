# Classes arabes, classe principale et classe secondaire (sous-chantier 1)

*(Validé par l'utilisateur le 2026-10-20.)*

## Contexte

- Une école franco-arabe (`establishments.is_arabe`) enseigne deux filières : française et arabe. La filière arabe a ses propres catalogues SaaS (`ArabicLevel`, `ArabicSerie`, `ArabicSubject`), séparés des catalogues français (spec du 2026-08-21).
- Aujourd'hui, un élève n'a qu'une classe française (`enrollments.classroom_id`, obligatoire). Son côté arabe est porté par `enrollments.arabic_level_id` / `arabic_serie_id`, qu'aucun écran ne renseigne. Les affectations d'enseignants, les grilles de notes et les bulletins arabes regroupent les élèves par couple niveau et série arabes.
- Les frais d'une inscription (inscription et 7 tranches) sont recopiés à sa création depuis la grille `level_fees` du **niveau français** de la classe (`LevelFeeDefaultsService`).
- Il n'existe aucun calcul automatique d'admission ou de redoublement. Le passage se fait à la réinscription de l'année suivante, en choisissant la nouvelle classe et en cochant « Redoublant ».
- Les tables arabes `teacher_arabic_level_subject`, `arabic_grade_sheets`, `arabic_grades` et `arabic_report_cards` sont vides en local, et aucune inscription n'a de niveau arabe.

## Découpage du chantier « classes arabes »

1. **Ce document** : classes arabes, classe principale et classe secondaire, frais arabes, filière arabe par classe.
2. Liste PDF d'une classe arabe.
3. Emploi du temps des classes arabes.
4. Appel par classe arabe (dépend du 3).

## Décisions validées

| Sujet | Décision |
|---|---|
| Unité de travail arabe | La classe arabe (niveau arabe + série si requise + numéro), comme en français |
| Classe principale | Française ou arabe, choisie **pour chaque élève** à l'inscription |
| Rôle de la classe principale | Détermine les frais et la classe « officielle » affichée |
| Passage | Indépendant dans chaque filière : un élève peut passer en français et redoubler en arabe |
| Classe secondaire | Obligatoire dans le principe, mais peut être ajoutée après l'inscription ; les élèves sans classe secondaire sont signalés |
| Frais | Une grille de frais par niveau arabe ; l'élève paie uniquement selon le niveau de sa classe principale |
| Placement | Depuis la fiche élève |
| Admission automatique | Hors périmètre (n'existe pas non plus en français) |

## Modèle de données

### Nouvelle table `arabic_classrooms`

| Colonne | Détail |
|---|---|
| `id` | |
| `establishment_id` | FK, `cascadeOnDelete` |
| `school_year_id` | FK |
| `arabic_level_id` | FK |
| `arabic_serie_id` | FK, nullable (obligatoire si le niveau exige une série) |
| `numero` | chaîne, 2 caractères max |
| `name` | généré : libellé français du niveau (`wording_fr`, à défaut `wording`), série éventuelle (`serie`), numéro (ex. « 1ère année A ») ; lisible sur les écrans financiers et les reçus en français, les écrans arabes affichant en plus le libellé arabe du niveau (`dir="rtl"`) |
| `capacity` | entier nullable |
| `timestamps` | |

- Unicité sur (`establishment_id`, `school_year_id`, `arabic_level_id`, `arabic_serie_id`, `numero`), index au nom court explicite.
- **Pas de `SoftDeletes`** : une ligne supprimée en douceur bloquerait encore la contrainte unique.
- Modèle `App\Domain\Arabic\Models\ArabicClassroom` avec `TenantScoped`, relations `schoolYear`, `arabicLevel`, `arabicSerie`, `enrollments`.

### Table `enrollments`

| Changement | Détail |
|---|---|
| `classroom_id` | devient **nullable** (FK inchangée) |
| `arabic_classroom_id` | nouveau, nullable, FK vers `arabic_classrooms`, `restrictOnDelete` |
| `main_track` | nouveau, chaîne `francais` / `arabe`, défaut `francais`, avec l'enum `App\Domain\Enrollment\Enums\Track` |
| `is_arabic_repeating` | nouveau, booléen, défaut `false` ; `is_repeating` reste celui de la filière française |
| `arabic_level_id`, `arabic_serie_id` | **supprimés** (se déduisent de la classe arabe) |

La classe de la filière principale doit être renseignée. La règle est vérifiée à l'enregistrement, pas par une contrainte de base, car elle dépend de `main_track`.

Méthodes de `Enrollment` :
- `mainClassroom(): Classroom|ArabicClassroom|null` et `secondaryClassroom(): Classroom|ArabicClassroom|null`, selon `main_track` ;
- `mainClassroomName(): string` (« — » si absente), et `mainLevelLabel(): string`, utilisés par les écrans d'affichage ;
- relation `arabicClassroom()`.

Les classes françaises et arabes exposent une méthode commune `levelLabel(): string`, sans interface lourde.

### Grille de frais arabe

- `arabic_level_fees` : `establishment_id`, `school_year_id`, `arabic_level_id`, `registration_amount`, colonnes de synchronisation, `SoftDeletes` (comme `level_fees`) ; unicité (`establishment_id`, `school_year_id`, `arabic_level_id`).
- `arabic_level_fee_installments` : `arabic_level_fee_id`, `installment_id`, `amount` (comme `level_fee_installments`).
- **Pas de montant « affecté »** : l'affectation par l'État ne concerne que le secondaire français.
- `LevelFeeDefaultsService` reçoit `arabicDefaultsFor(int $schoolYearId, int $arabicLevelId): array`, qui renvoie la même structure que `defaultsFor()`.

### Tables arabes existantes

- `teacher_arabic_level_subject` : `arabic_level_id` et `arabic_serie_id` remplacés par `arabic_classroom_id` ; l'unicité devient (`user_id`, `arabic_classroom_id`, `arabic_subject_id`, `school_year_id`).
- `arabic_grade_sheets` : `arabic_level_id` et `arabic_serie_id` remplacés par `arabic_classroom_id`.
- `arabic_subject_coefficients` : **inchangée**. Les coefficients restent définis par niveau et série. Pour une classe, on lit ceux du niveau et de la série de la classe.
- `arabic_grades`, `arabic_report_cards`, `arabic_terms` : inchangées. Elles référencent l'inscription ou la période.

### Migration

- Elle vérifie d'abord que `teacher_arabic_level_subject` et `arabic_grade_sheets` sont vides, et que plus aucune inscription n'a `arabic_level_id` renseigné. Sinon elle s'arrête avec un message explicite (`RuntimeException`), sans rien modifier.
- Elle retire les clés étrangères avant les index (MySQL).
- Elle donne des noms d'index courts, à cause de la limite de 64 caractères de MySQL.
- Elle n'a pas de DDL dans `DB::transaction()`.
- Elle est validée par `migrate:fresh` sur une base MySQL jetable, en plus de SQLite (tests).

## Écrans et parcours

### Classes arabes (`/arabic/classrooms`, `arabic.classrooms.index`)

- Composant `App\Livewire\Arabic\Classrooms\Index`, calqué sur `Academics\Classrooms\Index` : niveau arabe, série (liste affichée et obligatoire si le niveau l'exige), numéro, capacité. L'année est l'année courante et le nom est généré.
- Le tableau affiche le nombre d'élèves inscrits.
- `ArabicClassroomPolicy` : mêmes droits que `ClassroomPolicy`, en exigeant en plus `is_arabe` sur l'établissement courant.
- Entrée « Classes » en tête du groupe de menu « Arabe ».
- Une classe qui a des inscrits ne peut pas être supprimée. Le message est « Impossible de supprimer une classe qui a des élèves inscrits. »

### Inscription (fiche élève, `Students\Show`)

École franco-arabe :
1. Un choix « Filière principale » (Française / Arabe), français par défaut.
2. « Classe principale » : liste des classes de la filière principale de l'année. **Obligatoire.**
3. « Classe secondaire » : liste des classes de l'autre filière. Facultative.
4. Une case « Redoublant » par filière. « Boursier », « Interne » et « Affecté » ne sont proposés que si la classe principale est française du secondaire, comme aujourd'hui.
5. Les frais sont préremplis depuis la grille française (`defaultsFor`, niveau français) ou arabe (`arabicDefaultsFor`, niveau arabe), selon la filière principale.

École non franco-arabe : formulaire actuel inchangé. Côté serveur, `main_track` est forcé à `francais`, et `arabic_classroom_id` comme `is_arabic_repeating` sont mis à vide, quoi qu'envoie le navigateur.

Après l'inscription :
- La filière et la classe principales restent figées, comme aujourd'hui (retrait puis nouvelle inscription pour les changer).
- Un bouton « Classe secondaire » sur la ligne de l'inscription ouvre un petit formulaire pour ajouter, changer ou retirer la classe secondaire et régler son « Redoublant ». Les frais ne sont pas modifiés.
- Le badge « Classe secondaire à renseigner » s'affiche quand elle manque dans une école franco-arabe.

### Liste des élèves (`Students\Index`)

Dans une école franco-arabe :
- les colonnes « Classe principale » et « Classe secondaire » ;
- le filtre « Sans classe secondaire ».

### Frais de scolarité (`Billing\TuitionFees\Index`)

- Dans une école franco-arabe, deux onglets : « Niveaux français » (l'écran actuel) et « Niveaux arabes ».
- L'onglet arabe permet la même saisie (inscription et tranches) et la même copie depuis une autre année, sans le montant « affecté ».
- `ArabicLevelFeePolicy` reprend les droits de `LevelFeePolicy`, en exigeant en plus `is_arabe`.

### Affichage de la classe « officielle »

Les écrans qui affichent la classe d'un élève passent par `mainClassroomName()` ou `mainLevelLabel()`, au lieu de `$enrollment->classroom->…` :
- la fiche élève (classe principale et classe secondaire) ;
- la liste des élèves ;
- le suivi des paiements ;
- les remises (le niveau de `DiscountService` vient de la classe principale, en français ou en arabe) ;
- les reçus de paiement et les relances ;
- les bilans financiers ;
- le tableau de bord ;
- la recherche par UID ;
- le portail parents (tableau de bord, scolarité).

Les écrans propres à la filière française restent sur `classroom_id` et ignorent naturellement les élèves sans classe française : classes, notes, bulletins, appel, emplois du temps, cartes et listes PDF des classes françaises.

La carte d'élève individuelle (`StudentIdCardPdfController`) affiche la classe principale.

### Filière arabe par classe

- **Affectations des enseignants** (`Arabic\TeacherAssignments\Index`) : on choisit l'enseignant, la classe arabe et la matière arabe.
- **Grilles de notes** (`Arabic\GradeSheets\Index` et `Enter`) :
  - le filtre et la création se font par classe arabe ;
  - la liste des élèves de la saisie contient les inscriptions actives qui ont `arabic_classroom_id` égal à la classe, que l'arabe soit leur filière principale ou secondaire ;
  - le coefficient est lu par niveau et série de la classe.
- **Bulletins** (`Arabic\ReportCards\Index`, PDF) : la génération se fait par classe arabe et par période, et le rang se calcule dans la classe arabe.
- **Droits** : `User::isAssignedToArabicGroup()` est remplacé par `isAssignedToArabicClassroom(int $arabicClassroomId, ?int $arabicSubjectId = null)`. `ArabicGradeSheetPolicy` l'utilise.

## Règles vérifiées côté serveur

- La classe principale appartient à la filière `main_track`, et la classe secondaire à l'autre.
- Les deux classes appartiennent à l'établissement courant et à l'année de l'inscription.
- Une école non franco-arabe a toujours `main_track = francais` et `arabic_classroom_id = null`.
- Les écrans arabes exigent `is_arabe`. Une école qui cesse d'être franco-arabe garde ses données, mais n'accède plus à ces écrans.

## Traductions

Les nouveaux textes passent par `__()`, avec la phrase française comme clé, traduite dans `lang/en.json` et `lang/ar.json`.

## Tests (Pest)

- **Classes arabes** :
  - création, avec le nom généré ;
  - unicité ;
  - série obligatoire selon le niveau ;
  - accès refusé à une école non franco-arabe ;
  - suppression refusée quand la classe a des élèves.
- **Inscription** :
  - classe principale arabe sans classe française ;
  - classe principale française avec classe arabe secondaire ;
  - frais préremplis depuis la grille arabe ;
  - classe de la mauvaise filière rejetée ;
  - classe d'un autre établissement rejetée ;
  - école non franco-arabe forcée en français ;
  - ajout, changement et retrait de la classe secondaire ;
  - « Redoublant » arabe.
- **Liste des élèves** : le filtre « Sans classe secondaire ».
- **Frais arabes** : saisie, copie depuis une autre année, accès réservé.
- **Affichage** : le suivi des paiements, le reçu et le portail parents affichent la classe arabe principale ; aucune erreur pour un élève sans classe française.
- **Remises** : le niveau de référence est celui de la classe principale.
- **Filière arabe** :
  - les affectations, les grilles de notes et la saisie se font par classe arabe ;
  - l'élève dont l'arabe est la filière secondaire est bien présent ;
  - le bulletin et le rang se calculent dans la classe ;
  - un enseignant non affecté à la classe est refusé.
- **Écrans français** : ils ignorent les inscriptions sans classe française.
- **Migration** : elle s'arrête s'il existe des affectations ou des grilles arabes.
- Les tests existants restent verts. Larastan et Pint passent sur les fichiers modifiés.

## Déploiement

- `php artisan migrate`. La migration s'arrête si des données arabes de regroupement existent déjà en production.
- Aucune clé `.env`.

## Hors périmètre

- Liste PDF d'une classe arabe (sous-chantier 2).
- Emploi du temps arabe (sous-chantier 3).
- Appel par classe arabe (sous-chantier 4).
- Calcul automatique « admis » ou « redouble » à partir des moyennes.
- Changement de la filière principale d'une inscription existante (on passe par le retrait puis une nouvelle inscription).
