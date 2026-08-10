# Évaluations : distinction primaire/secondaire

## Contexte

Le domaine Grading existe déjà (`GradeSheet`/`Grade`/`ReportCard`, écrans de saisie, bulletins PDF, 18 tests verts) mais traite toutes les classes gradables (primaire + secondaire) de façon identique : une évaluation est un simple couple (titre, poids libre), et la moyenne générale du bulletin pool toutes les notes de l'élève sans distinguer les matières entre elles. Le champ `Subject.coefficient_default` existe en base mais n'est jamais lu par `ReportCardService`.

Ce chantier introduit la distinction attendue entre les deux cycles :

- **Secondaire** : deux types d'évaluation (devoir, interrogation), coefficient de matière qui dépend du niveau **et** de la série (ex. Maths coef. 4 en série C, coef. 2 en série A). Périodes = trimestres ou semestres (déjà supporté par `Term`, sans changement).
- **Primaire** : un seul type d'évaluation (composition), coefficient de matière qui dépend uniquement du niveau (pas de série). Périodes = "compositions" (généralement 4 par an), qui jouent le même rôle architectural que les trimestres du secondaire — pas de changement de schéma sur `Term`, seulement du contenu (label "Composition 1" au lieu de "Trimestre 1").

## 1. Types d'évaluation

Nouveau champ `GradeSheet.type` (string) : `devoir` | `interrogation` | `composition`.

- Classe secondaire : l'utilisateur choisit `devoir` ou `interrogation` à la création. Poids par défaut pré-rempli selon le type — `devoir` → 2, `interrogation` → 1 — mais le champ `weight` existant reste modifiable manuellement, comme aujourd'hui.
- Classe primaire : `type` est imposé à `composition` (pas de sélecteur affiché dans le formulaire), poids par défaut 1, modifiable.
- Le formulaire de création (`Livewire\Grading\GradeSheets\Index`) s'adapte selon le cycle de la classe sélectionnée (`Classroom->level->cycle`).

La formule de calcul de la moyenne de matière **ne change pas** : pool pondéré existant (`Σ(note/barème×20×poids) / Σ(poids)`), tous types d'évaluation confondus sur la période. Le type ne sert qu'à structurer la saisie et pré-remplir le poids — pas à appliquer une règle de pondération catégorielle stricte.

## 2. Grille de coefficients de matière

Nouvelle table `subject_coefficients`, une ligne par (établissement, niveau, série optionnelle, matière) :

```php
Schema::create('subject_coefficients', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('level_id')->constrained()->cascadeOnDelete();
    $table->foreignId('serie_id')->nullable()->constrained()->cascadeOnDelete();
    $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
    $table->decimal('coefficient', 4, 2);

    $table->char('uid', 12)->nullable()->unique();
    $table->uuid('device_id')->nullable();
    $table->timestamp('client_updated_at')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->index(['establishment_id', 'level_id', 'serie_id', 'subject_id']);
});
```

Pas de contrainte unique en base (nullable `serie_id` rend une contrainte unique peu fiable sous MySQL — deux NULL ne s'y considèrent jamais égaux) : l'unicité par (niveau, série, matière) est garantie côté application via `updateOrCreate` dans l'écran de configuration, seul point d'entrée en écriture.

`App\Domain\Academics\Models\SubjectCoefficient` — `HasFactory`, `SoftDeletes`, `Syncable`, `TenantScoped`, relations `level()`/`serie()`/`subject()`.

`Subject.coefficient_default` est retiré (nouvelle migration `drop_coefficient_default_from_subjects_table`) — entièrement remplacé par cette grille.

### Écran "Coefficients par matière"

Nouveau `Livewire\Academics\SubjectCoefficients\Index` (route `academics.subject-coefficients.index`, nav "Académique") — même gouvernance que `SubjectPolicy` (`isAdminOfCurrentEstablishment`, cohérent avec la configuration de structure académique existante, pas les rôles de saisie de notes) :

- Sélecteur de niveau (et de série si `level.requires_series`).
- Tableau des matières avec un champ coefficient éditable par ligne, `updateOrCreate(['establishment_id' => ..., 'level_id' => ..., 'serie_id' => ..., 'subject_id' => ...], ['coefficient' => ...])`.

## 3. Calcul de la moyenne générale

`ReportCardService::generateForClassroomAndTerm()` est réécrit :

1. Pour chaque élève, regrouper ses notes par matière (comme aujourd'hui via `subjectBreakdown`).
2. Pour chaque matière où l'élève a au moins une note : résoudre le coefficient via `SubjectCoefficient` pour `(classroom.level_id, classroom.serie_id, subject_id)`.
3. **Si un coefficient est manquant** pour une matière ayant des notes sur cette classe/période, la génération du lot de bulletins échoue avec un message explicite (`"Coefficient manquant pour {matière} (niveau {niveau}{, série}"`) — aucun `ReportCard` n'est créé/modifié pour ce lot tant que la grille n'est pas complétée. Cohérent avec le principe déjà établi ailleurs dans le projet de ne jamais générer un résultat financier/académique silencieusement faux.
4. `moyenne_générale = Σ(moyenne_matière × coefficient_matière) / Σ(coefficient_matière)`, arrondie à 2 décimales.
5. Rang par classe/période inchangé (classement par compétition, ex-æquo partagent le même rang).

`subjectBreakdown()` (détail par matière affiché sur le bulletin PDF) affiche désormais aussi le coefficient de chaque matière à côté de sa moyenne.

## Hors périmètre

- Pas de rattachement de `Term` à un cycle : le modèle reste générique, seul le contenu (labels, nombre de périodes) diffère entre primaire et secondaire par convention d'usage.
- Pas de coefficient par défaut de secours (ex. 1.0) si la grille est incomplète — génération bloquée plutôt que bulletin silencieusement faux.
- Pas de changement au périmètre de saisie des notes (`Enter.php`) ni aux règles d'accès existantes (`grades.enter`, affectation enseignant) — uniquement l'ajout du type et son effet sur le poids par défaut.
- Pas de migration automatique des `GradeSheet` existantes vers un type par défaut au-delà d'une valeur de repli technique nécessaire pour la colonne `NOT NULL` (à trancher en phase de plan selon les données de seed actuelles, hors décision produit).
